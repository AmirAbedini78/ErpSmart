<?php

namespace App\Services\Builder;

use App\Models\BuilderPublishAuditLog;
use App\Models\BuilderPublishExecution;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class BuilderRuntimeWritePlanArtifactService
{
    protected const FORBIDDEN_RUNTIME_PATHS = [
        'app/Core',
        'modules/Core',
        'modules/SaaS',
        'modules/Updater',
        'modules/Installer',
        'modules/Warehouse',
        'vendor',
        'node_modules',
        'public/build',
        '.env',
        'composer.json',
        'package.json',
        'routes/web.php',
        'resources/js/app.js',
        'database/migrations',
    ];

    protected const ALLOWED_RUNTIME_PATH_PREFIXES = [
        'App/Models',
        'App/Http/Controllers',
        'App/Http/Resources',
        'database/migrations',
        'resources/js',
        'routes',
    ];

    public function plan(BuilderPublishExecution $execution): array
    {
        $execution->loadMissing('definition', 'approvalRequest');

        $checks = [];
        $blockers = [];
        $warnings = [];
        $plannedWrites = [];

        $metadata = $execution->metadata_json ?: [];
        $stagedValidationReportPath = (string) ($metadata['staged_file_validation_path'] ?? '');
        $stagingRoot = (string) $execution->staging_root;
        $rollbackManifestPath = (string) $execution->rollback_manifest_path;
        $planRoot = 'storage/app/builder-runtime-write-plans/'.$execution->builder_definition_id.'/'.$execution->getKey();
        $planPath = $planRoot.'/runtime-write-plan.json';
        $moduleName = $this->moduleName($execution);

        $this->addCheck($checks, 'execution_status_staging_validated', $execution->status === BuilderPublishExecution::STATUS_STAGING_VALIDATED, true, 'Execution status must be staging_validated.', $blockers);
        $this->addCheck($checks, 'staged_validation_report_exists', $this->pathStartsWith($stagedValidationReportPath, 'storage/app/builder-publish-staged-validations/'.$execution->builder_definition_id.'/'.$execution->getKey()) && is_file(base_path($stagedValidationReportPath)), true, 'Staged validation report must exist under storage.', $blockers);

        $stagedValidationReport = $this->readJsonIfAllowed($stagedValidationReportPath, 'storage/app/builder-publish-staged-validations/'.$execution->builder_definition_id.'/'.$execution->getKey());
        $this->addCheck($checks, 'staged_validation_report_valid_json', is_array($stagedValidationReport), true, 'Staged validation report JSON must be valid.', $blockers);
        $this->addCheck($checks, 'staging_root_under_storage', $this->pathStartsWith($stagingRoot, 'storage/app/builder-publish-staging/'.$execution->builder_definition_id.'/'.$execution->getKey()), true, 'Staging root must be under storage/app/builder-publish-staging for this execution.', $blockers);
        $this->addCheck($checks, 'rollback_manifest_under_storage', $this->pathStartsWith($rollbackManifestPath, 'storage/app/builder-publish-rollbacks/'.$execution->builder_definition_id.'/'.$execution->getKey()), true, 'Rollback manifest must be under storage/app/builder-publish-rollbacks for this execution.', $blockers);
        $this->addCheck($checks, 'runtime_writes_zero', true, true, 'Runtime writes remain zero.', $blockers);
        $this->addCheck($checks, 'publish_executed_false', true, true, 'Publish is not executed by runtime write planning.', $blockers);

        if (is_array($stagedValidationReport)) {
            foreach (($stagedValidationReport['files'] ?? []) as $file) {
                if (($file['absolute_scope'] ?? null) !== 'staging') {
                    continue;
                }

                $plannedWrites[] = $this->plannedWrite($file, $moduleName, $blockers, $warnings);
            }
        }

        $this->addCheck($checks, 'runtime_path_allowlist_applied', true, true, 'Every planned future runtime path is checked against the allowlist.', $blockers);
        $this->addCheck($checks, 'no_path_traversal', ! $this->hasPathTraversal($plannedWrites), true, 'No staged or future runtime path may contain path traversal.', $blockers);
        $this->addCheck($checks, 'no_absolute_external_paths', ! $this->hasAbsoluteExternalPath($plannedWrites), true, 'Future runtime paths must be project-relative.', $blockers);
        $this->addCheck($checks, 'no_forbidden_paths', ! $this->hasForbiddenRuntimePath($plannedWrites), true, 'Future runtime paths must not target forbidden path families.', $blockers);
        $this->addCheck($checks, 'backup_required_for_overwrite', $this->overwritesRequireBackup($plannedWrites), true, 'Every planned overwrite must require backup before runtime write.', $blockers);
        $this->addCheck($checks, 'migrations_are_planned_only', $this->migrationsArePlannedOnly($plannedWrites), true, 'Migration files are planned only and will not be executed in this phase.', $blockers);

        $safe = $blockers === [];
        $status = $safe
            ? BuilderPublishExecution::STATUS_RUNTIME_WRITE_PLANNED
            : BuilderPublishExecution::STATUS_RUNTIME_WRITE_PLAN_BLOCKED;

        $report = [
            'execution_id' => $execution->getKey(),
            'status' => $status,
            'safe' => $safe,
            'writes_performed' => 0,
            'runtime_writes_performed' => 0,
            'publish_executed' => false,
            'copy_to_runtime_executed' => false,
            'runtime_module_effect' => 'none',
            'runtime_write_plan_path' => $planPath,
            'staging_root' => $stagingRoot,
            'staged_validation_report_path' => $stagedValidationReportPath,
            'rollback_manifest_path' => $rollbackManifestPath,
            'planned_writes' => $plannedWrites,
            'summary' => $this->summary($plannedWrites),
            'checks' => $checks,
            'blockers' => array_values(array_unique($blockers)),
            'warnings' => array_values(array_unique($warnings)),
            'forbidden_actions' => [
                'copy_to_runtime',
                'run_migrations',
                'register_routes',
                'mark_published',
                'execute_rollback',
            ],
            'next_allowed_actions' => [
                'review runtime write plan',
                'regenerate staged validation',
                'future explicit runtime write implementation',
            ],
        ];

        File::ensureDirectoryExists(base_path($planRoot));
        File::put(base_path($planPath), json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->updateRollbackManifestDraft($rollbackManifestPath, $report);

        $metadata['runtime_write_plan_path'] = $planPath;
        $metadata['runtime_write_plan_report'] = $report;

        $execution->fill([
            'status' => $status,
            'failure_reason' => $safe ? null : implode('; ', $report['blockers']),
            'metadata_json' => $metadata,
        ])->save();

        $this->logAudit($execution->fresh(), $safe ? 'runtime_write_plan_created' : 'runtime_write_plan_blocked', [
            'runtime_write_plan_path' => $planPath,
            'safe' => $safe,
            'blockers' => $report['blockers'],
            'runtime_writes_performed' => 0,
            'publish_executed' => false,
            'copy_to_runtime_executed' => false,
        ]);

        return $report;
    }

    protected function plannedWrite(array $file, string $moduleName, array &$blockers, array &$warnings): array
    {
        $sourceRelativePath = str_replace('\\', '/', ltrim((string) ($file['relative_path'] ?? ''), '/'));
        $futureRuntimePath = $this->futureRuntimePath($sourceRelativePath, $moduleName);
        $runtimePathAllowed = $this->runtimePathAllowed($futureRuntimePath, $moduleName);
        $pathTraversal = str_contains($sourceRelativePath, '..') || str_contains($futureRuntimePath, '..');
        $absoluteExternal = str_starts_with($futureRuntimePath, '/') || preg_match('/^[A-Za-z]:[\/\\\\]/', $futureRuntimePath) === 1;
        $forbidden = $this->matchesForbiddenRuntimePath($futureRuntimePath);

        if ($pathTraversal) {
            $blockers[] = "Path traversal detected for planned write: {$sourceRelativePath}";
        }

        if ($absoluteExternal) {
            $blockers[] = "External absolute path detected for planned write: {$futureRuntimePath}";
        }

        if (! $runtimePathAllowed || $forbidden) {
            $blockers[] = "Future runtime path is not allowed: {$futureRuntimePath}";
        }

        if ($futureRuntimePath === '') {
            $warnings[] = "No future runtime path could be mapped for staged artifact: {$sourceRelativePath}";
        }

        $writeAction = $this->writeAction($futureRuntimePath);

        return [
            'source_relative_path' => $sourceRelativePath,
            'source_sha256' => $file['sha256'] ?? null,
            'future_runtime_path' => $futureRuntimePath,
            'runtime_path_allowed' => $runtimePathAllowed && ! $forbidden && ! $pathTraversal && ! $absoluteExternal,
            'write_action' => $writeAction,
            'backup_required' => $writeAction === 'overwrite',
            'migration_execution_allowed_in_this_phase' => false,
            'runtime_written' => false,
        ];
    }

    protected function futureRuntimePath(string $sourceRelativePath, string $moduleName): string
    {
        if ($this->matchesForbiddenRuntimePath($sourceRelativePath)) {
            return $sourceRelativePath;
        }

        $filename = basename($sourceRelativePath);
        $cleanFilename = preg_replace('/\.stub$/', '', $filename) ?: $filename;

        if (str_starts_with($sourceRelativePath, 'backend/')) {
            if (str_contains($sourceRelativePath, 'Migration')) {
                return "modules/{$moduleName}/database/migrations/{$cleanFilename}";
            }

            if (str_contains($sourceRelativePath, 'Controller')) {
                return "modules/{$moduleName}/App/Http/Controllers/{$cleanFilename}";
            }

            if (str_contains($sourceRelativePath, 'JsonResource')) {
                return "modules/{$moduleName}/App/Http/Resources/{$cleanFilename}";
            }

            if (str_contains($sourceRelativePath, 'routes')) {
                return "modules/{$moduleName}/routes/{$cleanFilename}";
            }

            return "modules/{$moduleName}/App/Models/{$cleanFilename}";
        }

        if (str_starts_with($sourceRelativePath, 'frontend/')) {
            return "modules/{$moduleName}/resources/js/".substr($sourceRelativePath, strlen('frontend/'));
        }

        if (str_starts_with($sourceRelativePath, 'manifest/') || str_ends_with($sourceRelativePath, '.json') || str_ends_with($sourceRelativePath, '.md')) {
            return "docs/ai/generated-manifests/{$moduleName}/".str_replace('/', '-', $sourceRelativePath);
        }

        return "modules/{$moduleName}/resources/js/".str_replace('/', '-', $sourceRelativePath);
    }

    protected function writeAction(string $futureRuntimePath): string
    {
        if (str_contains($futureRuntimePath, '/database/migrations/')) {
            return 'planned_migration';
        }

        if ($futureRuntimePath === '') {
            return 'skip';
        }

        return file_exists(base_path($futureRuntimePath)) ? 'overwrite' : 'create';
    }

    protected function runtimePathAllowed(string $path, string $moduleName): bool
    {
        if ($path === '' || $this->matchesForbiddenRuntimePath($path) || str_contains($path, '..')) {
            return false;
        }

        if (str_starts_with($path, "docs/ai/generated-manifests/{$moduleName}/")) {
            return true;
        }

        foreach (self::ALLOWED_RUNTIME_PATH_PREFIXES as $prefix) {
            if (str_starts_with($path, "modules/{$moduleName}/{$prefix}/") || $path === "modules/{$moduleName}/{$prefix}") {
                return true;
            }
        }

        return false;
    }

    protected function moduleName(BuilderPublishExecution $execution): string
    {
        $definitionJson = $execution->definition?->definition_json ?: [];
        $candidate = (string) (
            $execution->definition?->module_name
            ?: data_get($definitionJson, 'module.name')
            ?: data_get($definitionJson, 'module.namespace')
            ?: 'GeneratedModule'
        );

        $studly = Str::studly($candidate);

        return $studly !== '' ? $studly : 'GeneratedModule';
    }

    protected function summary(array $plannedWrites): array
    {
        $summary = [
            'total_planned_writes' => count($plannedWrites),
            'creates' => 0,
            'overwrites' => 0,
            'skips' => 0,
            'planned_migrations' => 0,
            'blocked' => 0,
        ];

        foreach ($plannedWrites as $write) {
            match ($write['write_action'] ?? null) {
                'create' => $summary['creates']++,
                'overwrite' => $summary['overwrites']++,
                'skip' => $summary['skips']++,
                'planned_migration' => $summary['planned_migrations']++,
                default => null,
            };

            if (($write['runtime_path_allowed'] ?? false) !== true) {
                $summary['blocked']++;
            }
        }

        return $summary;
    }

    protected function updateRollbackManifestDraft(string $path, array $report): void
    {
        $manifest = $this->readJsonIfAllowed($path, 'storage/app/builder-publish-rollbacks/');

        if (! is_array($manifest)) {
            return;
        }

        $manifest['runtime_write_plan'] = [
            'runtime_write_plan_path' => $report['runtime_write_plan_path'],
            'planned_writes' => $report['planned_writes'],
            'summary' => $report['summary'],
            'runtime_writes_performed' => 0,
            'publish_executed' => false,
            'copy_to_runtime_executed' => false,
            'updated_at' => now()->toIso8601String(),
        ];

        File::put(base_path($path), json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    protected function addCheck(array &$checks, string $key, bool $passed, bool $required, string $message, array &$blockers): void
    {
        $status = $passed ? 'passed' : ($required ? 'blocked' : 'warning');
        $checks[] = compact('key', 'status', 'required', 'message');

        if (! $passed && $required) {
            $blockers[] = $message;
        }
    }

    protected function readJsonIfAllowed(string $path, string $prefix): ?array
    {
        if (! $this->pathStartsWith($path, $prefix)) {
            return null;
        }

        $fullPath = base_path($path);
        if (! is_file($fullPath)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($fullPath), true);

        return is_array($decoded) ? $decoded : null;
    }

    protected function pathStartsWith(string $path, string $prefix): bool
    {
        $normalized = str_replace('\\', '/', ltrim($path, '/'));

        return $normalized === rtrim($prefix, '/') || str_starts_with($normalized, rtrim($prefix, '/').'/');
    }

    protected function hasPathTraversal(array $plannedWrites): bool
    {
        foreach ($plannedWrites as $write) {
            if (str_contains((string) ($write['source_relative_path'] ?? ''), '..') || str_contains((string) ($write['future_runtime_path'] ?? ''), '..')) {
                return true;
            }
        }

        return false;
    }

    protected function hasAbsoluteExternalPath(array $plannedWrites): bool
    {
        foreach ($plannedWrites as $write) {
            $path = (string) ($write['future_runtime_path'] ?? '');
            if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1) {
                return true;
            }
        }

        return false;
    }

    protected function hasForbiddenRuntimePath(array $plannedWrites): bool
    {
        foreach ($plannedWrites as $write) {
            if ($this->matchesForbiddenRuntimePath((string) ($write['future_runtime_path'] ?? ''))) {
                return true;
            }
        }

        return false;
    }

    protected function matchesForbiddenRuntimePath(string $path): bool
    {
        $normalized = str_replace('\\', '/', ltrim($path, '/'));

        foreach (self::FORBIDDEN_RUNTIME_PATHS as $forbidden) {
            if ($normalized === $forbidden || str_starts_with($normalized, rtrim($forbidden, '/').'/')) {
                return true;
            }
        }

        return false;
    }

    protected function overwritesRequireBackup(array $plannedWrites): bool
    {
        foreach ($plannedWrites as $write) {
            if (($write['write_action'] ?? null) === 'overwrite' && ($write['backup_required'] ?? false) !== true) {
                return false;
            }
        }

        return true;
    }

    protected function migrationsArePlannedOnly(array $plannedWrites): bool
    {
        foreach ($plannedWrites as $write) {
            if (($write['write_action'] ?? null) === 'planned_migration' && ($write['migration_execution_allowed_in_this_phase'] ?? true) !== false) {
                return false;
            }
        }

        return true;
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
                'publish_executed' => false,
                'copy_to_runtime_executed' => false,
            ], $payload),
            'created_at' => now(),
        ]);
    }
}
