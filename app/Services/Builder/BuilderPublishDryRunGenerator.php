<?php

namespace App\Services\Builder;

use App\Models\BuilderDefinition;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class BuilderPublishDryRunGenerator
{
    public function __construct(
        protected BuilderDefinitionValidator $validator,
        protected BuilderPublishReadinessAnalyzer $readinessAnalyzer
    ) {
    }

    public function generate(BuilderDefinition $definition): array
    {
        $definitionJson = $definition->definition_json ?: [];
        $validation = $this->validator->validate($definitionJson);
        $readiness = $this->readinessAnalyzer->analyze($definition);
        $runId = now()->format('YmdHis').'-'.Str::lower(Str::random(8));
        $dryRunRoot = 'storage/app/builder-publish-dry-runs/'.$definition->getKey().'/'.$runId;
        $absoluteRoot = base_path($dryRunRoot);
        $files = [];
        $warnings = array_values(array_unique(array_merge(
            $validation['warnings'] ?? [],
            $readiness['warnings'] ?? []
        )));
        $blockers = array_values(array_unique(array_merge(
            $validation['valid'] ?? false ? [] : ['Definition validation failed.'],
            $readiness['blockers'] ?? []
        )));

        File::ensureDirectoryExists($absoluteRoot);

        $this->writeDryRunFile($dryRunRoot, 'README.md', $this->readme($definition), 'readme', 'README.md', $files);
        $this->writeDryRunFile($dryRunRoot, 'definition.json', $this->json($definitionJson), 'definition', 'builder-definition.json', $files);
        $this->writeDryRunFile($dryRunRoot, 'publish-readiness-report.json', $this->json($readiness), 'readiness', 'builder-publish-readiness-report.json', $files);
        $this->writeDryRunFile($dryRunRoot, 'future-file-plan.json', $this->json($readiness['file_plan'] ?? []), 'file_plan', 'future-file-plan.json', $files);
        $this->writeDryRunFile($dryRunRoot, 'backend/Model.php.stub', $this->modelStub($definitionJson), 'model', $this->futurePath($definitionJson, 'model'), $files);
        $this->writeDryRunFile($dryRunRoot, 'backend/Migration.php.stub', $this->migrationStub($definitionJson), 'migration', 'database/migrations/future_create_'.Arr::get($definitionJson, 'module.table', 'table').'_table.php', $files);
        $this->writeDryRunFile($dryRunRoot, 'backend/Controller.php.stub', $this->controllerStub($definitionJson), 'controller', $this->futurePath($definitionJson, 'controller'), $files);
        $this->writeDryRunFile($dryRunRoot, 'backend/JsonResource.php.stub', $this->jsonResourceStub($definitionJson), 'resource', $this->futurePath($definitionJson, 'json_resource'), $files);
        $this->writeDryRunFile($dryRunRoot, 'backend/routes-api.php.stub', $this->routesStub($definitionJson), 'routes', $this->futurePath($definitionJson, 'routes'), $files);
        $this->writeDryRunFile($dryRunRoot, 'frontend/routes.js.stub', $this->frontendRoutesStub($definitionJson), 'frontend_route', $this->futurePath($definitionJson, 'frontend_routes'), $files);
        $this->writeDryRunFile($dryRunRoot, 'frontend/IndexView.vue.stub', $this->vueStub($definitionJson, 'Index'), 'view', $this->futurePath($definitionJson, 'index_view'), $files);
        $this->writeDryRunFile($dryRunRoot, 'frontend/DetailView.vue.stub', $this->vueStub($definitionJson, 'Detail'), 'view', $this->futurePath($definitionJson, 'detail_view'), $files);

        $artifactSummary = $this->artifactSummary($files);
        $review = $this->review($validation, $readiness, $files, $warnings, $blockers);

        $manifest = [
            'generated_at' => now()->toIso8601String(),
            'definition_id' => $definition->getKey(),
            'definition_name' => $definition->name,
            'definition_checksum' => $definition->checksum,
            'run_id' => $runId,
            'dry_run_root' => $dryRunRoot,
            'writes_performed' => 0,
            'runtime_writes_performed' => 0,
            'dry_run_artifacts_written' => count($files) + 1,
            'publish_executed' => false,
            'runtime_module_effect' => 'none',
            'validation' => $validation,
            'readiness' => $readiness,
            'files' => $files,
            'warnings' => $warnings,
            'blockers' => $blockers,
            'review' => $review,
            'artifact_summary' => $artifactSummary,
            'safety' => [
                'sandbox_only' => true,
                'runtime_paths_touched' => false,
                'migrations_run' => false,
                'publish_executed' => false,
            ],
        ];

        $this->writeDryRunFile($dryRunRoot, 'manifest/publish-dry-run-manifest.json', $this->json($manifest), 'manifest', 'publish-dry-run-manifest.json', $files);
        $manifest['files'] = $files;
        $manifest['dry_run_artifacts_written'] = count($files);
        $manifest['artifact_summary'] = $this->artifactSummary($files);

        File::put(base_path($dryRunRoot.'/manifest/publish-dry-run-manifest.json'), $this->json($manifest));

        return $manifest;
    }

    protected function writeDryRunFile(string $root, string $path, string $contents, string $type, string $futureRuntimePath, array &$files): void
    {
        $absolutePath = base_path($root.'/'.$path);

        File::ensureDirectoryExists(dirname($absolutePath));
        File::put($absolutePath, $contents);

        $files[] = [
            'type' => $type,
            'future_runtime_path' => $futureRuntimePath,
            'dry_run_path' => $root.'/'.$path,
            'status' => 'generated_in_sandbox',
            'runtime_written' => false,
        ];
    }

    protected function review(array $validation, array $readiness, array $files, array $warnings, array $blockers): array
    {
        $unsupported = $readiness['capability_impact']['unsupported'] ?? [];
        $formLayoutEnabled = (bool) ($readiness['form_layout_impact']['enabled'] ?? false);
        $automationEnabled = (bool) ($readiness['automation_impact']['enabled'] ?? false);

        return [
            'review_ready' => true,
            'requires_human_approval' => true,
            'approval_status' => 'not_requested',
            'approval_checklist' => [
                $this->checklistItem('validation_passed', 'Definition validation passed', ($validation['valid'] ?? false) ? 'passed' : 'blocked', true),
                $this->checklistItem('no_runtime_writes', 'Runtime writes are zero', 'passed', true),
                $this->checklistItem('no_migrations_run', 'No migrations were run', 'passed', true),
                $this->checklistItem('no_runtime_routes_registered', 'No runtime routes were registered', 'passed', true),
                $this->checklistItem('readiness_analyzer_completed', 'Publish readiness analyzer completed', isset($readiness['status']) ? 'passed' : 'blocked', true),
                $this->checklistItem('dry_run_manifest_valid', 'Dry-run manifest was generated', $files !== [] ? 'passed' : 'blocked', true),
                $this->checklistItem('blockers_empty', 'Blockers are empty', $blockers === [] ? 'passed' : 'blocked', true),
                $this->checklistItem('unsupported_capabilities_reviewed', 'Unsupported capabilities reviewed', $unsupported === [] ? 'passed' : 'warning', true, implode(', ', $unsupported)),
                $this->checklistItem('form_layout_metadata_reviewed', 'Form layout metadata reviewed', $formLayoutEnabled ? 'warning' : 'passed', true, $formLayoutEnabled ? 'Metadata only; runtime renderer is future work.' : ''),
                $this->checklistItem('automation_metadata_reviewed', 'Automation metadata reviewed', $automationEnabled ? 'warning' : 'passed', true, $automationEnabled ? 'Metadata only; runtime execution is forbidden in MVP.' : ''),
                $this->checklistItem('rollback_requirements_reviewed', 'Rollback requirements reviewed', ($readiness['rollback_requirements'] ?? []) !== [] ? 'warning' : 'not_checked', true),
                $this->checklistItem('human_approval_required_before_future_publish', 'Human approval required before future publish', 'not_checked', true),
            ],
            'safety_checklist' => [
                $this->checklistItem('runtime_writes_zero', 'Runtime writes are zero', 'passed', true),
                $this->checklistItem('no_migrations_run', 'No migrations were run', 'passed', true),
                $this->checklistItem('no_runtime_routes_registered', 'No runtime routes were registered', 'passed', true),
                $this->checklistItem('sandbox_only', 'Dry-run artifacts are sandbox-only', 'passed', true),
                $this->checklistItem('publish_not_available', 'Publish is not available in this MVP', 'passed', true),
            ],
            'next_allowed_actions' => [
                'review dry-run artifacts',
                'regenerate dry-run',
                'archive definition',
            ],
            'forbidden_actions' => [
                'publish',
                'copy artifacts into runtime paths',
                'run migrations',
                'drop tables',
            ],
        ];
    }

    protected function checklistItem(string $key, string $label, string $status, bool $required, string $notes = ''): array
    {
        return compact('key', 'label', 'status', 'required', 'notes');
    }

    protected function artifactSummary(array $files): array
    {
        $byType = [];
        foreach ($files as $file) {
            $type = (string) ($file['type'] ?? 'unknown');
            $byType[$type] = ($byType[$type] ?? 0) + 1;
        }

        return [
            'total_files' => count($files),
            'by_type' => $byType,
            'future_runtime_paths' => array_values(array_map(fn (array $file): string => (string) $file['future_runtime_path'], $files)),
            'dry_run_paths' => array_values(array_map(fn (array $file): string => (string) $file['dry_run_path'], $files)),
        ];
    }

    protected function readme(BuilderDefinition $definition): string
    {
        return "# DRY RUN ONLY - NOT RUNTIME CODE\n\n".
            "Generated under storage for review only.\n\n".
            "Do not copy to production without publish pipeline.\n\n".
            "Definition: {$definition->name}\n";
    }

    protected function modelStub(array $definition): string
    {
        $model = class_basename((string) Arr::get($definition, 'resource.modelClass', 'GeneratedModel'));
        $fillable = collect(Arr::get($definition, 'fields', []))
            ->pluck('name')
            ->reject(fn ($name) => $name === 'id')
            ->map(fn ($name) => "'".$name."'")
            ->implode(', ');

        return "<?php\n\n// DRY RUN ONLY - NOT RUNTIME CODE\n// Generated under storage for review only\n// Do not copy to production without publish pipeline\n\nclass {$model}\n{\n    protected array \$fillable = [{$fillable}];\n}\n";
    }

    protected function migrationStub(array $definition): string
    {
        $table = (string) Arr::get($definition, 'module.table', 'generated_table');
        $lines = collect(Arr::get($definition, 'fields', []))
            ->reject(fn (array $field) => ($field['type'] ?? '') === 'id')
            ->map(fn (array $field) => '            // '.$field['type'].' '.$field['name'])
            ->implode("\n");

        return "<?php\n\n// DRY RUN ONLY - NOT RUNTIME CODE\n// Generated under storage for review only\n// Do not copy to production without publish pipeline\n\nreturn 'future migration plan for {$table}';\n\n/*\n{$lines}\n*/\n";
    }

    protected function controllerStub(array $definition): string
    {
        $module = (string) Arr::get($definition, 'module.name', 'GeneratedModule');

        return "<?php\n\n// DRY RUN ONLY - NOT RUNTIME CODE\n// Generated under storage for review only\n// Do not copy to production without publish pipeline\n\nclass {$module}Controller\n{\n    // Future resource controller placeholder.\n}\n";
    }

    protected function jsonResourceStub(array $definition): string
    {
        $fields = collect(Arr::get($definition, 'fields', []))
            ->pluck('name')
            ->map(fn ($name) => "            '{$name}' => \$this->{$name},")
            ->implode("\n");

        return "<?php\n\n// DRY RUN ONLY - NOT RUNTIME CODE\n// Generated under storage for review only\n// Do not copy to production without publish pipeline\n\nreturn [\n{$fields}\n];\n";
    }

    protected function routesStub(array $definition): string
    {
        $route = (string) Arr::get($definition, 'module.routeName', 'generated-route');

        return "<?php\n\n// DRY RUN ONLY - NOT RUNTIME CODE\n// Generated under storage for review only\n// Do not copy to production without publish pipeline\n\n// Future API routes for {$route}.\n";
    }

    protected function frontendRoutesStub(array $definition): string
    {
        $route = (string) Arr::get($definition, 'module.routeName', 'generated-route');

        return "// DRY RUN ONLY - NOT RUNTIME CODE\n// Generated under storage for review only\n// Do not copy to production without publish pipeline\n\nexport default [{ path: '/{$route}', name: '{$route}-index' }]\n";
    }

    protected function vueStub(array $definition, string $type): string
    {
        $label = (string) Arr::get($definition, 'module.pluralLabel', Arr::get($definition, 'module.name', 'Generated'));

        return "<!-- DRY RUN ONLY - NOT RUNTIME CODE -->\n<!-- Generated under storage for review only -->\n<!-- Do not copy to production without publish pipeline -->\n<template>\n  <div>{$label} {$type} dry run</div>\n</template>\n";
    }

    protected function futurePath(array $definition, string $kind): string
    {
        $module = (string) Arr::get($definition, 'module.name', 'GeneratedModule');
        $model = class_basename((string) Arr::get($definition, 'resource.modelClass', 'GeneratedModel'));

        return match ($kind) {
            'model' => 'modules/'.$module.'/app/Models/'.$model.'.php',
            'controller' => 'modules/'.$module.'/app/Http/Controllers/'.$model.'Controller.php',
            'json_resource' => 'modules/'.$module.'/app/Http/Resources/'.$model.'Resource.php',
            'routes' => 'modules/'.$module.'/routes/api.php',
            'frontend_routes' => 'modules/'.$module.'/resources/js/routes.js',
            'index_view' => 'modules/'.$module.'/resources/js/views/'.$model.'Index.vue',
            'detail_view' => 'modules/'.$module.'/resources/js/views/'.$model.'View.vue',
            default => 'modules/'.$module,
        };
    }

    protected function json(array $value): string
    {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
