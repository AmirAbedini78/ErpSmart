<?php

namespace App\Services\Builder;

use App\Models\BuilderDefinition;
use App\Models\BuilderPublishApprovalRequest;
use App\Models\BuilderPublishAuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BuilderPublishApprovalService
{
    public function __construct(protected BuilderPublishCandidateSnapshotService $candidateSnapshots)
    {
    }

    public function requestApproval(BuilderDefinition $definition, ?array $input = []): BuilderPublishApprovalRequest
    {
        return DB::transaction(function () use ($definition, $input): BuilderPublishApprovalRequest {
            $snapshot = $this->candidateSnapshots->create($definition);

            $request = BuilderPublishApprovalRequest::create([
                'uuid' => (string) Str::uuid(),
                'builder_definition_id' => $definition->getKey(),
                'status' => BuilderPublishApprovalRequest::STATUS_REQUESTED,
                'candidate_id' => $snapshot['candidate_id'],
                'candidate_snapshot_path' => $snapshot['candidate_snapshot_path'],
                'candidate_root' => $snapshot['candidate_root'],
                'definition_checksum' => $definition->checksum,
                'requested_by_id' => auth()->id(),
                'requested_at' => now(),
                'expires_at' => $input['expires_at'] ?? null,
                'snapshot_json' => $snapshot,
                'metadata_json' => [
                    'approval_does_not_publish' => true,
                    'publish_executed' => false,
                    'runtime_writes_performed' => 0,
                ],
            ]);

            $this->logAudit($request, 'approval_requested', [
                'approval_does_not_publish' => true,
                'candidate_snapshot_path' => $request->candidate_snapshot_path,
            ]);

            return $request->fresh(['definition', 'auditLogs']);
        });
    }

    public function approve(BuilderPublishApprovalRequest $request, ?string $note = null): BuilderPublishApprovalRequest
    {
        $request->loadMissing('definition');

        if (! $request->canApprove()) {
            throw ValidationException::withMessages(['status' => 'Only requested approval requests can be approved.']);
        }

        if (! $request->isChecksumCurrent($request->definition)) {
            return DB::transaction(function () use ($request): BuilderPublishApprovalRequest {
                $request->fill([
                    'status' => BuilderPublishApprovalRequest::STATUS_INVALIDATED,
                    'invalidated_at' => now(),
                    'invalidation_reason' => 'Definition checksum changed before approval.',
                ])->save();

                $this->logAudit($request, 'approval_invalidated', [
                    'reason' => $request->invalidation_reason,
                    'current_definition_checksum' => $request->definition->checksum,
                    'approval_definition_checksum' => $request->definition_checksum,
                ]);

                return $request->fresh(['definition', 'auditLogs']);
            });
        }

        return DB::transaction(function () use ($request, $note): BuilderPublishApprovalRequest {
            $request->fill([
                'status' => BuilderPublishApprovalRequest::STATUS_APPROVED,
                'approved_by_id' => auth()->id(),
                'approved_at' => now(),
                'decision_note' => $note,
            ])->save();

            $this->logAudit($request, 'approval_approved', [
                'decision_note' => $note,
                'approval_does_not_publish' => true,
            ]);

            return $request->fresh(['definition', 'auditLogs']);
        });
    }

    public function reject(BuilderPublishApprovalRequest $request, ?string $note = null): BuilderPublishApprovalRequest
    {
        if (! $request->canReject()) {
            throw ValidationException::withMessages(['status' => 'Only requested approval requests can be rejected.']);
        }

        return DB::transaction(function () use ($request, $note): BuilderPublishApprovalRequest {
            $request->fill([
                'status' => BuilderPublishApprovalRequest::STATUS_REJECTED,
                'rejected_by_id' => auth()->id(),
                'rejected_at' => now(),
                'decision_note' => $note,
            ])->save();

            $this->logAudit($request, 'approval_rejected', [
                'decision_note' => $note,
                'approval_does_not_publish' => true,
            ]);

            return $request->fresh(['definition', 'auditLogs']);
        });
    }

    public function revoke(BuilderPublishApprovalRequest $request, ?string $note = null): BuilderPublishApprovalRequest
    {
        if (! $request->canRevoke()) {
            throw ValidationException::withMessages(['status' => 'Only requested or approved approval requests can be revoked.']);
        }

        return DB::transaction(function () use ($request, $note): BuilderPublishApprovalRequest {
            $request->fill([
                'status' => BuilderPublishApprovalRequest::STATUS_REVOKED,
                'revoked_by_id' => auth()->id(),
                'revoked_at' => now(),
                'decision_note' => $note,
            ])->save();

            $this->logAudit($request, 'approval_revoked', [
                'decision_note' => $note,
                'approval_does_not_publish' => true,
            ]);

            return $request->fresh(['definition', 'auditLogs']);
        });
    }

    public function logAudit(BuilderPublishApprovalRequest $request, string $eventType, array $payload = []): BuilderPublishAuditLog
    {
        return BuilderPublishAuditLog::create([
            'uuid' => (string) Str::uuid(),
            'builder_definition_id' => $request->builder_definition_id,
            'builder_publish_approval_request_id' => $request->getKey(),
            'candidate_id' => $request->candidate_id,
            'definition_checksum' => $request->definition_checksum,
            'event_type' => $eventType,
            'actor_id' => auth()->id(),
            'payload_json' => $payload,
            'created_at' => now(),
        ]);
    }
}
