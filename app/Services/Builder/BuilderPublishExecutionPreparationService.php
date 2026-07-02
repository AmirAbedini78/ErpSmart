<?php

namespace App\Services\Builder;

use App\Models\BuilderDefinition;
use App\Models\BuilderPublishAuditLog;
use App\Models\BuilderPublishExecution;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

class BuilderPublishExecutionPreparationService
{
    public function __construct(protected BuilderApprovedCandidatePreflightService $preflight)
    {
    }

    public function prepare(BuilderDefinition $definition): array
    {
        $execution = BuilderPublishExecution::create([
            'uuid' => (string) Str::uuid(),
            'builder_definition_id' => $definition->getKey(),
            'status' => BuilderPublishExecution::STATUS_REQUESTED,
            'definition_checksum' => $definition->checksum,
            'requested_by_id' => auth()->id(),
            'started_at' => now(),
            'metadata_json' => [
                'control_plane_only' => true,
                'runtime_writes_performed' => 0,
                'publish_executed' => false,
            ],
        ]);

        $auditEvents = [];
        $auditEvents[] = $this->logAudit($execution, 'publish_preflight_started', [
            'control_plane_only' => true,
            'publish_executed' => false,
        ])->event_type;

        $lockKey = 'builder:publish:'.$definition->getKey();
        $lockOwner = (string) Str::uuid();
        $lock = Cache::lock($lockKey, 120);
        $lockAcquired = false;
        try {
            $lockAcquired = (bool) $lock->get();

            if (! $lockAcquired) {
                $execution->fill([
                    'status' => BuilderPublishExecution::STATUS_FAILED,
                    'lock_key' => $lockKey,
                    'lock_owner' => $lockOwner,
                    'failed_at' => now(),
                    'failure_reason' => 'Publish preparation lock failed.',
                ])->save();

                $auditEvents[] = $this->logAudit($execution, 'publish_lock_failed', [
                    'lock_key' => $lockKey,
                    'publish_executed' => false,
                ])->event_type;

                return $this->report($execution->fresh(), false, false, null, $auditEvents);
            }

            $execution->fill([
                'status' => BuilderPublishExecution::STATUS_LOCK_ACQUIRED,
                'lock_key' => $lockKey,
                'lock_owner' => $lockOwner,
                'lock_acquired_at' => now(),
            ])->save();

            $auditEvents[] = $this->logAudit($execution, 'publish_lock_acquired', [
                'lock_key' => $lockKey,
                'lock_owner' => $lockOwner,
                'publish_executed' => false,
            ])->event_type;

            $preflight = $this->preflight->check($definition->fresh());
            $approval = $preflight['approval_request'] ?? null;
            $candidateId = is_array($approval) ? ($approval['candidate_id'] ?? null) : null;
            $candidateSnapshotPath = is_array($approval) ? ($approval['candidate_snapshot_path'] ?? null) : null;
            $approvalRequestId = is_array($approval) ? ($approval['id'] ?? null) : null;

            $execution->fill([
                'builder_publish_approval_request_id' => $approvalRequestId,
                'candidate_id' => $candidateId,
                'candidate_snapshot_path' => $candidateSnapshotPath,
                'preflight_report_json' => $preflight,
                'preflight_completed_at' => now(),
            ])->save();

            if (($preflight['eligible_for_future_publish'] ?? false) !== true) {
                $blockers = $preflight['blockers'] ?? ['Approved candidate preflight did not pass.'];

                $execution->fill([
                    'status' => BuilderPublishExecution::STATUS_PREFLIGHT_FAILED,
                    'failed_at' => now(),
                    'failure_reason' => implode('; ', array_map('strval', $blockers)),
                ])->save();

                $auditEvents[] = $this->logAudit($execution, 'publish_preflight_failed', [
                    'blockers' => $blockers,
                    'publish_executed' => false,
                ])->event_type;

                $lock->release();
                $auditEvents[] = $this->logAudit($execution->fresh(), 'publish_lock_released', [
                    'lock_key' => $lockKey,
                    'lock_owner' => $lockOwner,
                    'publish_executed' => false,
                ])->event_type;

                return $this->report($execution->fresh(), true, true, $preflight, $auditEvents);
            }

            $stagingRoot = 'storage/app/builder-publish-staging/'.$definition->getKey().'/'.$execution->getKey();
            $rollbackRoot = 'storage/app/builder-publish-rollbacks/'.$definition->getKey().'/'.$execution->getKey();
            $rollbackManifestPath = $rollbackRoot.'/rollback-manifest.json';

            File::ensureDirectoryExists(base_path($stagingRoot));
            File::ensureDirectoryExists(base_path($rollbackRoot));

            $rollbackManifest = [
                'generated_at' => now()->toIso8601String(),
                'status' => 'draft',
                'execution_id' => $execution->getKey(),
                'publish_executed' => false,
                'runtime_writes_performed' => 0,
                'runtime_module_effect' => 'none',
                'definition_id' => $definition->getKey(),
                'definition_checksum' => $definition->checksum,
                'candidate_id' => $candidateId,
                'candidate_snapshot_path' => $candidateSnapshotPath,
                'files_to_create' => [],
                'files_to_modify' => [],
                'files_to_delete_if_rollback' => [],
                'pre_publish_file_hashes' => [],
                'pre_publish_file_backups' => [],
                'migration_plan' => [],
                'migration_status' => 'not_run',
                'created_tables' => [],
                'modified_tables' => [],
                'route_menu_permission_changes' => [],
                'cache_keys_affected' => [],
                'search_vector_rag_indexes_affected' => [],
                'smoke_test_results' => [],
                'audit_events' => [],
                'safety' => [
                    'draft_only' => true,
                    'runtime_paths_touched' => false,
                    'migrations_run' => false,
                    'publish_executed' => false,
                ],
            ];

            File::put(base_path($rollbackManifestPath), json_encode($rollbackManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $execution->fill([
                'status' => BuilderPublishExecution::STATUS_PREFLIGHT_PASSED,
                'rollback_manifest_path' => $rollbackManifestPath,
                'staging_root' => $stagingRoot,
            ])->save();

            $auditEvents[] = $this->logAudit($execution, 'rollback_manifest_created', [
                'rollback_manifest_path' => $rollbackManifestPath,
                'runtime_writes_performed' => 0,
            ])->event_type;
            $auditEvents[] = $this->logAudit($execution, 'publish_staging_created', [
                'staging_root' => $stagingRoot,
                'runtime_writes_performed' => 0,
            ])->event_type;

            $lock->release();
            $auditEvents[] = $this->logAudit($execution->fresh(), 'publish_lock_released', [
                'lock_key' => $lockKey,
                'lock_owner' => $lockOwner,
                'publish_executed' => false,
            ])->event_type;

            return $this->report($execution->fresh(), true, true, $preflight, $auditEvents);
        } catch (Throwable $exception) {
            if ($lockAcquired) {
                $lock->release();

                $auditEvents[] = $this->logAudit($execution->fresh(), 'publish_lock_released', [
                    'lock_key' => $lockKey,
                    'lock_owner' => $lockOwner,
                    'publish_executed' => false,
                ])->event_type;
            }

            $execution->fill([
                'status' => BuilderPublishExecution::STATUS_FAILED,
                'failed_at' => now(),
                'failure_reason' => $exception->getMessage(),
            ])->save();

            $auditEvents[] = $this->logAudit($execution->fresh(), 'publish_preflight_failed', [
                'failure_reason' => $exception->getMessage(),
                'publish_executed' => false,
            ])->event_type;

            return $this->report($execution->fresh(), $lockAcquired, $lockAcquired, null, $auditEvents);
        }
    }

    protected function report(
        BuilderPublishExecution $execution,
        bool $lockAcquired,
        bool $lockReleased,
        ?array $preflight,
        array $auditEvents
    ): array {
        return [
            'execution_id' => $execution->getKey(),
            'status' => $execution->status,
            'writes_performed' => 0,
            'runtime_writes_performed' => 0,
            'publish_executed' => false,
            'runtime_module_effect' => 'none',
            'lock' => [
                'key' => $execution->lock_key,
                'acquired' => $lockAcquired,
                'released' => $lockReleased,
            ],
            'preflight' => $preflight ?? $execution->preflight_report_json,
            'rollback_manifest_path' => $execution->rollback_manifest_path,
            'staging_root' => $execution->staging_root,
            'audit_events' => array_values($auditEvents),
            'execution' => $execution->fresh(),
            'forbidden_actions' => [
                'runtime file write',
                'migration execution',
                'route registration',
                'mark published',
            ],
            'next_allowed_actions' => [
                'review execution record',
                'review rollback manifest draft',
                'cancel execution',
            ],
        ];
    }

    protected function logAudit(BuilderPublishExecution $execution, string $eventType, array $payload = []): BuilderPublishAuditLog
    {
        return BuilderPublishAuditLog::create([
            'uuid' => (string) Str::uuid(),
            'builder_definition_id' => $execution->builder_definition_id,
            'builder_publish_approval_request_id' => $execution->builder_publish_approval_request_id,
            'candidate_id' => $execution->candidate_id,
            'definition_checksum' => $execution->definition_checksum,
            'event_type' => $eventType,
            'actor_id' => auth()->id(),
            'payload_json' => array_merge([
                'builder_publish_execution_id' => $execution->getKey(),
                'control_plane_only' => true,
                'runtime_writes_performed' => 0,
            ], $payload),
            'created_at' => now(),
        ]);
    }
}
