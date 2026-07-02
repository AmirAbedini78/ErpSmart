<?php

namespace App\Services\Builder;

use App\Models\BuilderDefinition;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class BuilderPublishReadinessAnalyzer
{
    protected array $previewSafeCapabilities = [
        'tableable',
        'hasDetailView',
        'customFields',
        'uniqueCustomFields',
        'importable',
        'exportable',
        'cloneable',
        'bulkDelete',
        'globalSearch',
        'quickCreate',
        'floatingModal',
        'media',
        'mediable',
        'notes',
        'comments',
        'activities',
        'activityComments',
        'activityAssociations',
    ];

    protected array $metadataOnlyCapabilities = [
        'formLayout',
        'stepperForm',
        'sections',
        'conditionalVisibility',
        'workflow',
        'tasks',
        'emails',
        'emailSending',
        'approvals',
        'notifications',
    ];

    protected array $warningOnlyCapabilities = [
        'documents',
        'calls',
        'timeline',
        'softDeletes',
    ];

    public function __construct(protected BuilderDefinitionValidator $validator)
    {
    }

    public function analyze(BuilderDefinition $definition): array
    {
        $definitionJson = $definition->definition_json ?: [];
        $validation = $this->validator->validate($definitionJson);
        $moduleName = (string) Arr::get($definitionJson, 'module.name', $definition->module_name);
        $table = (string) Arr::get($definitionJson, 'module.table', '');
        $routeName = (string) Arr::get($definitionJson, 'module.routeName', $definition->resource_name);
        $resourceName = (string) Arr::get($definitionJson, 'module.resourceName', $definition->resource_name);
        $singular = (string) Arr::get($definitionJson, 'module.singularLabel', $definition->entity_name);
        $modulePath = base_path('modules/'.$moduleName);

        $conflicts = [];
        $blockers = [];
        $warnings = [];

        if ($moduleName !== '' && File::isDirectory($modulePath)) {
            $conflicts[] = [
                'type' => 'module_directory',
                'path' => 'modules/'.$moduleName,
                'severity' => 'blocking',
                'message' => 'A module directory already exists for '.$moduleName.'.',
            ];
        }

        if ($table !== '' && Schema::hasTable($table)) {
            $conflicts[] = [
                'type' => 'table',
                'name' => $table,
                'severity' => 'blocking',
                'message' => 'Database table '.$table.' already exists.',
            ];
        }

        foreach ($this->routeConflicts($routeName, $resourceName) as $routeConflict) {
            $conflicts[] = $routeConflict;
        }

        $capabilityImpact = $this->capabilityImpact($definitionJson);
        foreach ($capabilityImpact['metadata_only'] as $capability) {
            $warnings[] = $capability.' is metadata-only in the current Builder and needs a future runtime engine/renderer.';
        }
        foreach ($capabilityImpact['warning_only'] as $capability) {
            $warnings[] = $capability.' is known by schema but warning-only for publish readiness.';
        }
        foreach ($capabilityImpact['unsupported'] as $capability) {
            $warnings[] = $capability.' is not classified by the current analyzer.';
        }

        foreach ($this->relationImpact($definitionJson) as $relation) {
            if (! empty($relation['missing'])) {
                $warnings[] = 'Relation '.$relation['name'].' is missing '.implode(', ', $relation['missing']).'.';
            }
        }

        if (! ($validation['valid'] ?? false)) {
            $blockers[] = 'Definition validation failed.';
        }

        foreach ($conflicts as $conflict) {
            if (($conflict['severity'] ?? null) === 'blocking') {
                $blockers[] = $conflict['message'];
            }
        }

        $status = $blockers !== []
            ? 'blocked'
            : ($warnings !== [] || ($validation['warnings'] ?? []) !== [] ? 'warning' : 'ready');

        return [
            'safe' => $blockers === [],
            'status' => $status,
            'writes_performed' => 0,
            'runtime_module_effect' => 'none',
            'publish_executed' => false,
            'definition' => [
                'id' => $definition->getKey(),
                'name' => $definition->name,
                'status' => $definition->status,
                'module' => $moduleName,
                'table' => $table,
                'route' => $routeName ?: $resourceName,
            ],
            'validation' => $validation,
            'file_plan' => $this->filePlan($moduleName, $singular, $resourceName),
            'database_plan' => [
                'would_create_tables' => array_values(array_filter([$table])),
                'would_modify_tables' => [],
                'would_run_migrations' => false,
                'migration_required' => $table !== '',
            ],
            'route_menu_permission_plan' => [
                'routes' => $this->routePlan($routeName ?: $resourceName),
                'menus' => $moduleName !== '' ? ['Builder publish would plan a menu entry for '.$moduleName] : [],
                'permissions' => $resourceName !== '' ? [
                    $resourceName.'.view',
                    $resourceName.'.create',
                    $resourceName.'.update',
                    $resourceName.'.delete',
                ] : [],
            ],
            'capability_impact' => $capabilityImpact,
            'relation_impact' => $this->relationImpact($definitionJson),
            'conflicts' => $conflicts,
            'blockers' => array_values(array_unique($blockers)),
            'warnings' => array_values(array_unique(array_merge($warnings, $validation['warnings'] ?? []))),
            'rollback_requirements' => [
                'publish manifest',
                'generated file checksum manifest',
                'migration rollback or forward-fix plan',
                'database backup before schema changes',
                'route/menu/cache rebuild plan',
                'post-rollback smoke verifier',
            ],
            'dependency_checks' => [
                'module directory',
                'database table',
                'route names and URIs',
                'menu entry',
                'permissions',
                'policies',
                'relations and foreign keys',
                'capability generated artifacts',
                'RAG manifests and vector indexes',
                'backup and rollback manifest',
            ],
        ];
    }

    protected function filePlan(string $moduleName, string $singular, string $resourceName): array
    {
        if ($moduleName === '') {
            return [
                'would_create' => [],
                'would_modify' => [],
                'would_not_touch' => ['runtime modules', 'Core', 'Warehouse', 'vendor', 'public/build'],
            ];
        }

        $singular = $singular !== '' ? $singular : Str::singular($moduleName);
        $resourceName = $resourceName !== '' ? $resourceName : Str::kebab(Str::plural($singular));

        return [
            'would_create' => [
                'modules/'.$moduleName.'/module.json',
                'modules/'.$moduleName.'/bootstrap/module.php',
                'modules/'.$moduleName.'/app/Models/'.$singular.'.php',
                'modules/'.$moduleName.'/app/Resources/'.$singular.'.php',
                'modules/'.$moduleName.'/app/Http/Resources/'.$singular.'Resource.php',
                'modules/'.$moduleName.'/app/Policies/'.$singular.'Policy.php',
                'modules/'.$moduleName.'/routes/api.php',
                'modules/'.$moduleName.'/routes/web.php',
                'modules/'.$moduleName.'/resources/js/routes.js',
                'modules/'.$moduleName.'/resources/js/views/'.Str::studly($resourceName).'View.vue',
                'patches/verify_'.Str::snake($moduleName).'_'.Str::snake($singular).'_contract.php',
            ],
            'would_modify' => [
                'future module registration manifest',
                'future menu registration',
                'future permission registration',
            ],
            'would_not_touch' => [
                'modules/Warehouse',
                'modules/Core',
                'modules/SaaS',
                'modules/Updater',
                'modules/Installer',
                'vendor',
                'node_modules',
                'public/build',
            ],
        ];
    }

    protected function routePlan(string $routeName): array
    {
        if ($routeName === '') {
            return [];
        }

        return [
            'GET /'.$routeName,
            'POST /api/'.$routeName,
            'GET /api/'.$routeName.'/{id}',
            'PUT /api/'.$routeName.'/{id}',
            'DELETE /api/'.$routeName.'/{id}',
        ];
    }

    protected function routeConflicts(string $routeName, string $resourceName): array
    {
        $needles = array_values(array_filter([$routeName, $resourceName]));
        $conflicts = [];

        if ($needles === []) {
            return [];
        }

        foreach (Route::getRoutes() as $route) {
            $name = (string) $route->getName();
            $uri = $route->uri();

            foreach ($needles as $needle) {
                if ($needle !== '' && ($name === $needle || str_contains($uri, $needle))) {
                    $conflicts[] = [
                        'type' => 'route',
                        'name' => $name,
                        'uri' => $uri,
                        'severity' => 'warning',
                        'message' => 'Existing route may conflict with '.$needle.': '.$uri,
                    ];
                }
            }
        }

        return $conflicts;
    }

    protected function capabilityImpact(array $definition): array
    {
        $enabled = array_keys(array_filter(Arr::get($definition, 'capabilities', [])));

        return [
            'preview_safe' => array_values(array_intersect($enabled, $this->previewSafeCapabilities)),
            'metadata_only' => array_values(array_intersect($enabled, $this->metadataOnlyCapabilities)),
            'warning_only' => array_values(array_intersect($enabled, $this->warningOnlyCapabilities)),
            'unsupported' => array_values(array_diff(
                $enabled,
                $this->previewSafeCapabilities,
                $this->metadataOnlyCapabilities,
                $this->warningOnlyCapabilities
            )),
        ];
    }

    protected function relationImpact(array $definition): array
    {
        return array_map(function (array $relation): array {
            $missing = [];

            foreach (['name', 'type', 'targetModel', 'foreignKey'] as $field) {
                if (blank($relation[$field] ?? null)) {
                    $missing[] = $field;
                }
            }

            return [
                'name' => (string) ($relation['name'] ?? 'unnamed_relation'),
                'type' => (string) ($relation['type'] ?? 'unknown'),
                'target_module' => (string) ($relation['targetModule'] ?? ''),
                'target_model' => (string) ($relation['targetModel'] ?? ''),
                'target_resource' => (string) ($relation['targetResource'] ?? ''),
                'foreign_key' => (string) ($relation['foreignKey'] ?? ''),
                'show_on_detail' => (bool) ($relation['showOnDetail'] ?? false),
                'show_on_index' => (bool) ($relation['showOnIndex'] ?? false),
                'missing' => $missing,
            ];
        }, Arr::get($definition, 'relations', []));
    }
}
