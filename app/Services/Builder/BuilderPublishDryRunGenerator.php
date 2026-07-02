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
