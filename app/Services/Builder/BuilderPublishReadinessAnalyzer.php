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
        $identityChecks = $this->identityChecks($definitionJson);
        $existingAppConflicts = $this->existingAppConflicts($definitionJson, $moduleName, $table, $routeName, $resourceName);
        $fieldImpact = $this->fieldImpact($definitionJson);
        $relationImpact = $this->relationImpact($definitionJson);
        $formLayoutImpact = $this->formLayoutImpact($definitionJson);
        $automationImpact = $this->automationImpact($definitionJson);

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

        foreach ($existingAppConflicts['route_name_exists'] as $routeConflict) {
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

        foreach ($relationImpact['relations'] as $relation) {
            if (! empty($relation['missing'])) {
                $warnings[] = 'Relation '.$relation['name'].' is missing '.implode(', ', $relation['missing']).'.';
            }
        }

        foreach ($identityChecks['naming_warnings'] as $warning) {
            $warnings[] = $warning;
        }
        foreach ($fieldImpact['validation_rule_warnings'] as $warning) {
            $warnings[] = $warning;
        }
        if ($formLayoutImpact['metadata_only_warning']) {
            $warnings[] = $formLayoutImpact['metadata_only_warning'];
        }
        if ($automationImpact['metadata_only_warning']) {
            $warnings[] = $automationImpact['metadata_only_warning'];
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

        $artifactPath = $this->diagnosticArtifactPath($definition);

        $report = [
            'safe' => $blockers === [],
            'status' => $status,
            'writes_performed' => 0,
            'diagnostic_artifacts_written' => 1,
            'diagnostic_artifact_path' => $artifactPath,
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
            'identity_checks' => $identityChecks,
            'existing_app_conflicts' => $existingAppConflicts,
            'field_impact' => $fieldImpact,
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
            'relation_impact' => $relationImpact,
            'form_layout_impact' => $formLayoutImpact,
            'automation_impact' => $automationImpact,
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
            'publish_plan_artifact' => [
                'artifact_generated' => true,
                'path' => $artifactPath,
                'writes_performed' => 0,
                'artifact_write_only' => true,
                'runtime_module_effect' => 'none',
            ],
        ];

        $this->writeDiagnosticArtifact($definition, $report);

        return $report;
    }

    protected function identityChecks(array $definition): array
    {
        $fields = Arr::get($definition, 'fields', []);
        $titleField = (string) Arr::get($definition, 'resource.titleField', '');
        $primaryFields = array_values(array_filter($fields, fn (array $field): bool => (bool) ($field['primary'] ?? false)));
        $fieldNames = array_map(fn (array $field): string => (string) ($field['name'] ?? ''), $fields);
        $warnings = [];
        $moduleName = (string) Arr::get($definition, 'module.name', '');
        $table = (string) Arr::get($definition, 'module.table', '');
        $route = (string) Arr::get($definition, 'module.routeName', '');

        if ($moduleName !== '' && ! preg_match('/^[A-Z][A-Za-z0-9]*$/', $moduleName)) {
            $warnings[] = 'module.name should be PascalCase for generated module paths.';
        }
        if ($table !== '' && ! preg_match('/^[a-z][a-z0-9_]*$/', $table)) {
            $warnings[] = 'module.table should be snake_case.';
        }
        if ($route !== '' && ! preg_match('/^[a-z0-9-]+$/', $route)) {
            $warnings[] = 'module.routeName should be kebab-case.';
        }

        return [
            'module_name_present' => filled($moduleName),
            'namespace_present' => filled(Arr::get($definition, 'module.namespace')),
            'table_name_present' => filled($table),
            'route_name_present' => filled($route),
            'resource_name_present' => filled(Arr::get($definition, 'module.resourceName')),
            'title_field_exists' => $titleField !== '' && in_array($titleField, $fieldNames, true),
            'primary_field_exists' => $primaryFields !== [],
            'naming_warnings' => $warnings,
        ];
    }

    protected function existingAppConflicts(array $definition, string $moduleName, string $table, string $routeName, string $resourceName): array
    {
        $permissionNames = $resourceName !== '' ? [
            $resourceName.'.view',
            $resourceName.'.create',
            $resourceName.'.update',
            $resourceName.'.delete',
        ] : [];
        $classCandidates = array_values(array_filter([
            Arr::get($definition, 'resource.modelClass'),
            'Modules\\'.$moduleName.'\\'.$moduleName.'ServiceProvider',
            'Modules\\'.$moduleName.'\\Providers\\RouteServiceProvider',
        ]));

        return [
            'module_directory_exists' => $moduleName !== '' && File::isDirectory(base_path('modules/'.$moduleName)),
            'table_exists' => $table !== '' && Schema::hasTable($table),
            'route_name_exists' => $this->routeConflicts($routeName, $resourceName),
            'permission_name_conflicts' => [],
            'menu_entry_possible_conflicts' => $moduleName !== '' ? [
                'Potential menu entry label/order conflict for '.$moduleName.' requires publish-time menu registry check.',
            ] : [],
            'class_name_possible_conflicts' => array_values(array_filter($classCandidates, fn (string $class): bool => class_exists($class))),
            'permission_names_planned' => $permissionNames,
        ];
    }

    protected function fieldImpact(array $definition): array
    {
        $fields = Arr::get($definition, 'fields', []);
        $byType = [];
        $primary = [];
        $required = [];
        $selectWithoutOptions = [];
        $belongsTo = [];
        $ruleWarnings = [];

        foreach ($fields as $field) {
            $name = (string) ($field['name'] ?? '');
            $type = (string) ($field['type'] ?? 'unknown');
            $byType[$type] = ($byType[$type] ?? 0) + 1;

            if (($field['primary'] ?? false) === true) {
                $primary[] = $name;
            }
            if (($field['required'] ?? false) === true || in_array('required', $field['rules'] ?? [], true)) {
                $required[] = $name;
            }
            if ($type === 'select' && empty($field['options'])) {
                $selectWithoutOptions[] = $name;
            }
            if ($type === 'belongsTo') {
                $belongsTo[] = $name;
            }
            foreach (['rules', 'creationRules', 'updateRules'] as $ruleKey) {
                foreach (($field[$ruleKey] ?? []) as $rule) {
                    if (is_string($rule) && str_contains($rule, 'unique:')) {
                        $ruleWarnings[] = $name.' uses unique validation; publish must confirm table and update-rule behavior.';
                    }
                }
            }
        }

        return [
            'total_fields' => count($fields),
            'fields_by_type' => $byType,
            'primary_fields' => $primary,
            'required_fields' => $required,
            'select_fields_without_options' => $selectWithoutOptions,
            'belongs_to_fields' => $belongsTo,
            'validation_rule_warnings' => array_values(array_unique($ruleWarnings)),
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
        $impact = [
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

        $impact['capability_warnings'] = array_values(array_filter(array_merge(
            array_map(fn (string $capability): string => $capability.' is metadata-only and requires future runtime support.', $impact['metadata_only']),
            array_map(fn (string $capability): string => $capability.' is warning-only for publish readiness.', $impact['warning_only']),
            array_map(fn (string $capability): string => $capability.' is unsupported by the current analyzer.', $impact['unsupported'])
        )));

        return $impact;
    }

    protected function formLayoutImpact(array $definition): array
    {
        $layout = Arr::get($definition, 'formLayout', []);
        $fields = array_map(fn (array $field): string => (string) ($field['name'] ?? ''), Arr::get($definition, 'fields', []));
        $missing = [];

        foreach (Arr::get($layout, 'sections', []) as $section) {
            foreach (($section['fields'] ?? []) as $field) {
                $fieldName = (string) ($field['field'] ?? '');
                if ($fieldName !== '' && ! in_array($fieldName, $fields, true)) {
                    $missing[] = $fieldName;
                }
            }
        }
        foreach (Arr::get($layout, 'conditions', []) as $condition) {
            $fieldName = (string) ($condition['targetField'] ?? '');
            if ($fieldName !== '' && ! in_array($fieldName, $fields, true)) {
                $missing[] = $fieldName;
            }
        }

        return [
            'enabled' => (bool) Arr::get($layout, 'enabled', false),
            'sections_count' => count(Arr::get($layout, 'sections', [])),
            'stepper_enabled' => (bool) Arr::get($layout, 'stepper.enabled', false),
            'conditions_count' => count(Arr::get($layout, 'conditions', [])),
            'missing_field_references' => array_values(array_unique($missing)),
            'metadata_only_warning' => Arr::get($layout, 'enabled', false)
                ? 'formLayout is metadata-only; runtime form renderer is future work.'
                : null,
        ];
    }

    protected function automationImpact(array $definition): array
    {
        $automation = Arr::get($definition, 'automation', []);
        $fields = array_map(fn (array $field): string => (string) ($field['name'] ?? ''), Arr::get($definition, 'fields', []));
        $triggers = [];
        $actions = [];
        $missing = [];

        foreach (Arr::get($automation, 'workflows', []) as $workflow) {
            $triggerType = (string) Arr::get($workflow, 'trigger.type', 'unknown');
            $triggers[$triggerType] = ($triggers[$triggerType] ?? 0) + 1;
            $triggerField = (string) Arr::get($workflow, 'trigger.field', '');
            if ($triggerField !== '' && ! in_array($triggerField, $fields, true)) {
                $missing[] = $triggerField;
            }

            foreach (($workflow['conditions'] ?? []) as $condition) {
                $fieldName = (string) ($condition['field'] ?? '');
                if ($fieldName !== '' && ! in_array($fieldName, $fields, true)) {
                    $missing[] = $fieldName;
                }
            }

            foreach (($workflow['actions'] ?? []) as $action) {
                $actionType = (string) ($action['type'] ?? 'unknown');
                $actions[$actionType] = ($actions[$actionType] ?? 0) + 1;
            }
        }

        return [
            'enabled' => (bool) Arr::get($automation, 'enabled', false),
            'workflows_count' => count(Arr::get($automation, 'workflows', [])),
            'triggers_by_type' => $triggers,
            'actions_by_type' => $actions,
            'webhook_actions_count' => $actions['webhook'] ?? 0,
            'email_actions_count' => $actions['send_email'] ?? 0,
            'task_actions_count' => $actions['create_task'] ?? 0,
            'approval_actions_count' => $actions['request_approval'] ?? 0,
            'missing_field_references' => array_values(array_unique($missing)),
            'metadata_only_warning' => Arr::get($automation, 'enabled', false)
                ? 'automation is metadata-only; runtime workflow execution is forbidden in MVP.'
                : null,
            'runtime_execution_forbidden' => true,
        ];
    }

    protected function relationImpact(array $definition): array
    {
        $relations = array_map(function (array $relation): array {
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

        $types = [];
        foreach ($relations as $relation) {
            $types[$relation['type']] = ($types[$relation['type']] ?? 0) + 1;
        }

        return [
            'total_relations' => count($relations),
            'relation_types' => $types,
            'missing_target_model' => array_values(array_filter($relations, fn (array $relation): bool => in_array('targetModel', $relation['missing'], true))),
            'missing_target_resource' => array_values(array_filter($relations, fn (array $relation): bool => blank($relation['target_resource']))),
            'missing_foreign_key' => array_values(array_filter($relations, fn (array $relation): bool => in_array('foreignKey', $relation['missing'], true))),
            'target_module_conflicts' => array_values(array_filter($relations, fn (array $relation): bool => $relation['target_module'] !== '' && ! File::isDirectory(base_path('modules/'.$relation['target_module'])))),
            'relations' => $relations,
        ];
    }

    protected function writeDiagnosticArtifact(BuilderDefinition $definition, array $report): string
    {
        $relativePath = $this->diagnosticArtifactPath($definition);
        $absolutePath = base_path($relativePath);

        File::ensureDirectoryExists(dirname($absolutePath));
        File::put($absolutePath, json_encode([
            'generated_at' => now()->toIso8601String(),
            'definition_checksum' => $definition->checksum,
            'report' => $report,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $relativePath;
    }

    protected function diagnosticArtifactPath(BuilderDefinition $definition): string
    {
        return 'storage/app/builder-publish-readiness/'.$definition->getKey().'/publish-readiness-plan.json';
    }
}
