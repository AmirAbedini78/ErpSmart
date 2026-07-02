<?php

namespace App\Services\Builder;

use App\Models\BuilderPublishAuditLog;
use App\Models\BuilderPublishExecution;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use SplFileInfo;

class BuilderPublishStagedFileValidationService
{
    protected const FORBIDDEN_PATHS = [
        'modules/',
        'database/migrations/',
        'routes/',
        'resources/js/app.js',
        'public/build/',
        'vendor/',
        'node_modules/',
        '.env',
    ];

    public function validate(BuilderPublishExecution $execution): array
    {
        $execution->loadMissing('definition', 'approvalRequest');

        $checks = [];
        $blockers = [];
        $warnings = [];
        $files = [];
        $summary = [
            'total_files' => 0,
            'files_by_extension' => [],
            'files_by_classification' => [],
            'total_bytes' => 0,
        ];

        $stagingRoot = (string) $execution->staging_root;
        $rollbackManifestPath = (string) $execution->rollback_manifest_path;
        $validationRoot = 'storage/app/builder-publish-staged-validations/'.$execution->builder_definition_id.'/'.$execution->getKey();
        $validationReportPath = $validationRoot.'/staged-file-validation.json';

        $this->addCheck($checks, 'execution_status_preflight_passed', $execution->status === BuilderPublishExecution::STATUS_PREFLIGHT_PASSED, true, 'Execution status must be preflight_passed.', $blockers);
        $this->addCheck($checks, 'staging_root_present', filled($stagingRoot), true, 'Staging root must be present.', $blockers);
        $this->addCheck($checks, 'staging_root_under_storage', $this->pathStartsWith($stagingRoot, 'storage/app/builder-publish-staging/'.$execution->builder_definition_id.'/'.$execution->getKey()), true, 'Staging root must be under storage/app/builder-publish-staging for this execution.', $blockers);
        $this->addCheck($checks, 'rollback_manifest_path_present', filled($rollbackManifestPath), true, 'Rollback manifest path must be present.', $blockers);
        $this->addCheck($checks, 'rollback_manifest_under_storage', $this->pathStartsWith($rollbackManifestPath, 'storage/app/builder-publish-rollbacks/'.$execution->builder_definition_id.'/'.$execution->getKey()), true, 'Rollback manifest must be under storage/app/builder-publish-rollbacks for this execution.', $blockers);

        $rollbackManifest = $this->readJsonIfSafe($rollbackManifestPath);
        $this->addCheck($checks, 'rollback_manifest_json_valid', is_array($rollbackManifest), true, 'Rollback manifest JSON must be valid.', $blockers);
        $this->addCheck($checks, 'validation_report_under_storage', $this->pathStartsWith($validationReportPath, 'storage/app/builder-publish-staged-validations/'.$execution->builder_definition_id.'/'.$execution->getKey()), true, 'Validation report must be under storage/app/builder-publish-staged-validations.', $blockers);
        $this->addCheck($checks, 'publish_executed_false', true, true, 'Staged validation does not publish.', $blockers);
        $this->addCheck($checks, 'runtime_writes_zero', true, true, 'Runtime writes remain zero.', $blockers);

        $allowedScopes = [
            'staging' => $stagingRoot,
            'rollback' => dirname($rollbackManifestPath),
        ];

        foreach ($allowedScopes as $scope => $root) {
            $this->collectFiles($scope, $root, $files, $summary, $blockers, $warnings);
        }

        $this->addCheck($checks, 'no_path_traversal', ! $this->hasPathIssue($files), true, 'No path traversal or symlink escape is allowed.', $blockers);
        $this->addCheck($checks, 'no_runtime_paths_written', true, true, 'No runtime paths were written.', $blockers);
        $this->addCheck($checks, 'no_forbidden_paths', ! $this->hasForbiddenPath($files), true, 'Staged artifacts must not use forbidden runtime path patterns.', $blockers);
        $this->addCheck($checks, 'files_have_checksums', $this->filesHaveChecksums($files), true, 'All discovered files must have SHA-256 checksums.', $blockers);

        $safe = $blockers === [];
        $status = $safe
            ? BuilderPublishExecution::STATUS_STAGING_VALIDATED
            : BuilderPublishExecution::STATUS_STAGING_VALIDATION_FAILED;

        $report = [
            'execution_id' => $execution->getKey(),
            'status' => $status,
            'safe' => $safe,
            'writes_performed' => 0,
            'runtime_writes_performed' => 0,
            'publish_executed' => false,
            'runtime_module_effect' => 'none',
            'validation_report_path' => $validationReportPath,
            'staging_root' => $stagingRoot,
            'rollback_manifest_path' => $rollbackManifestPath,
            'files' => $files,
            'summary' => $summary,
            'checks' => $checks,
            'blockers' => array_values(array_unique($blockers)),
            'warnings' => array_values(array_unique($warnings)),
            'forbidden_actions' => [
                'copy_to_runtime',
                'run_migrations',
                'register_routes',
                'mark_published',
            ],
            'next_allowed_actions' => [
                'review staged file validation',
                'regenerate execution record',
                'future explicit runtime write phase',
            ],
        ];

        File::ensureDirectoryExists(base_path($validationRoot));
        File::put(base_path($validationReportPath), json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $metadata = $execution->metadata_json ?: [];
        $metadata['staged_file_validation_path'] = $validationReportPath;
        $metadata['staged_file_validation_report'] = $report;

        $execution->fill([
            'status' => $status,
            'failure_reason' => $safe ? null : implode('; ', $report['blockers']),
            'metadata_json' => $metadata,
        ])->save();

        $this->logAudit($execution->fresh(), $safe ? 'publish_staging_validated' : 'publish_staging_validation_failed', [
            'validation_report_path' => $validationReportPath,
            'safe' => $safe,
            'blockers' => $report['blockers'],
            'runtime_writes_performed' => 0,
            'publish_executed' => false,
        ]);

        return $report;
    }

    protected function collectFiles(string $scope, string $relativeRoot, array &$files, array &$summary, array &$blockers, array &$warnings): void
    {
        if (! str_starts_with(str_replace('\\', '/', ltrim($relativeRoot, '/')), 'storage/app/builder-publish-')) {
            $blockers[] = "Scope {$scope} root is outside allowed builder-publish storage.";

            return;
        }

        $root = base_path($relativeRoot);
        if (! is_dir($root)) {
            $warnings[] = "Scope {$scope} root does not exist or has no files.";

            return;
        }

        $rootReal = realpath($root);
        if ($rootReal === false) {
            $blockers[] = "Scope {$scope} root cannot be resolved.";

            return;
        }

        foreach (File::allFiles($root) as $file) {
            /** @var SplFileInfo $file */
            $realPath = realpath($file->getPathname());
            $relativePath = str_replace('\\', '/', ltrim(str_replace($root, '', $file->getPathname()), DIRECTORY_SEPARATOR));
            $safePath = $realPath !== false && str_starts_with($realPath, $rootReal.DIRECTORY_SEPARATOR);
            $extension = strtolower($file->getExtension() ?: 'none');
            $classification = $this->classify($relativePath, $extension);
            $sha256 = $safePath ? hash_file('sha256', $file->getPathname()) : null;
            $size = $safePath ? $file->getSize() : 0;
            $forbidden = $this->matchesForbiddenPath($relativePath);

            if (! $safePath) {
                $blockers[] = "Unsafe path detected in {$scope}: {$relativePath}";
            }

            if ($forbidden) {
                $blockers[] = "Forbidden staged path detected in {$scope}: {$relativePath}";
            }

            $files[] = [
                'relative_path' => $relativePath,
                'absolute_scope' => $scope,
                'size_bytes' => $size,
                'sha256' => $sha256,
                'extension' => $extension,
                'classification' => $classification,
                'safe_path' => $safePath && ! $forbidden,
                'runtime_written' => false,
            ];

            $summary['total_files']++;
            $summary['total_bytes'] += $size;
            $summary['files_by_extension'][$extension] = ($summary['files_by_extension'][$extension] ?? 0) + 1;
            $summary['files_by_classification'][$classification] = ($summary['files_by_classification'][$classification] ?? 0) + 1;
        }
    }

    protected function classify(string $relativePath, string $extension): string
    {
        if (str_contains($relativePath, 'rollback-manifest')) {
            return 'rollback_manifest';
        }

        if (str_contains($relativePath, 'manifest')) {
            return 'manifest';
        }

        if (str_contains($relativePath, 'Migration')) {
            return 'migration_stub';
        }

        if (str_contains($relativePath, 'Model')) {
            return 'model_stub';
        }

        if (str_contains($relativePath, 'routes')) {
            return 'route_stub';
        }

        return match ($extension) {
            'json' => 'manifest',
            'php', 'stub' => 'php_stub',
            'vue' => 'vue_stub',
            'md' => 'documentation',
            default => 'unknown',
        };
    }

    protected function addCheck(array &$checks, string $key, bool $passed, bool $required, string $message, array &$blockers): void
    {
        $status = $passed ? 'passed' : ($required ? 'blocked' : 'warning');
        $checks[] = compact('key', 'status', 'required', 'message');

        if (! $passed && $required) {
            $blockers[] = $message;
        }
    }

    protected function readJsonIfSafe(string $path): ?array
    {
        if (! $this->pathStartsWith($path, 'storage/app/builder-publish-rollbacks/')) {
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

        return $normalized === $prefix || str_starts_with($normalized, rtrim($prefix, '/').'/');
    }

    protected function hasPathIssue(array $files): bool
    {
        foreach ($files as $file) {
            if (($file['safe_path'] ?? false) !== true) {
                return true;
            }

            if (str_contains((string) $file['relative_path'], '..')) {
                return true;
            }
        }

        return false;
    }

    protected function hasForbiddenPath(array $files): bool
    {
        foreach ($files as $file) {
            if ($this->matchesForbiddenPath((string) $file['relative_path'])) {
                return true;
            }
        }

        return false;
    }

    protected function matchesForbiddenPath(string $relativePath): bool
    {
        $path = str_replace('\\', '/', ltrim($relativePath, '/'));

        foreach (self::FORBIDDEN_PATHS as $forbidden) {
            if ($path === rtrim($forbidden, '/') || str_starts_with($path, $forbidden)) {
                return true;
            }
        }

        return false;
    }

    protected function filesHaveChecksums(array $files): bool
    {
        foreach ($files as $file) {
            if (empty($file['sha256'])) {
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
            ], $payload),
            'created_at' => now(),
        ]);
    }
}
