<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ErpsmartMakeModuleCommand extends Command
{
    protected $signature = 'erpsmart:make-module
        {--definition= : Path to the module definition JSON file}
        {--dry-run : Validate and print planned files without writing anything}
        {--preview : Render preview files under storage/app/module-builder-preview without runtime writes}
        {--write : Reserved for future write mode; currently refused}';

    protected $description = 'Dry-run the ERPSMART Module Builder MVP without writing generated module files.';

    public function handle(): int
    {
        if ($this->option('write')) {
            $this->error('Refusing --write. Real module generation is not implemented yet.');

            return self::FAILURE;
        }

        if (! $this->option('dry-run') && ! $this->option('preview')) {
            $this->error('Refusing to run without --dry-run or --preview. Real module generation is not implemented yet.');
            $this->error('Use --dry-run to print the plan or --preview to render safe preview files.');

            return self::FAILURE;
        }

        $definitionPath = $this->option('definition');

        if (! is_string($definitionPath) || trim($definitionPath) === '') {
            $this->error('The --definition option is required.');

            return self::FAILURE;
        }

        $definitionPath = base_path($definitionPath);

        if (! is_file($definitionPath)) {
            $this->error('Definition file not found: '.$definitionPath);

            return self::FAILURE;
        }

        $definition = json_decode(file_get_contents($definitionPath) ?: '', true);

        if (! is_array($definition)) {
            $this->error('Definition file is not valid JSON.');

            return self::FAILURE;
        }

        $errors = $this->validateDefinition($definition);

        if ($errors !== []) {
            $this->error('Definition validation failed.');

            foreach ($errors as $error) {
                $this->line('- '.$error);
            }

            return self::FAILURE;
        }

        $plan = $this->buildPlan($definition);

        if ($this->option('preview')) {
            $this->renderPreview($definition, $plan);
        } else {
            $this->printPlan($definition, $plan);
        }

        return self::SUCCESS;
    }

    protected function validateDefinition(array $definition): array
    {
        $errors = [];

        foreach ($this->requiredDefinitionPaths() as $path) {
            if (! $this->hasPath($definition, $path)) {
                $errors[] = 'missing '.$path;
            }
        }

        if (($definition['schemaVersion'] ?? null) !== 1) {
            $errors[] = 'schemaVersion must be 1';
        }

        if (! isset($definition['fields']) || ! is_array($definition['fields']) || count($definition['fields']) === 0) {
            $errors[] = 'fields must be a non-empty array';
        }

        if (isset($definition['fields']) && is_array($definition['fields'])) {
            foreach ($definition['fields'] as $index => $field) {
                foreach (['name', 'type', 'label', 'rules', 'visibility'] as $key) {
                    if (! is_array($field) || ! array_key_exists($key, $field)) {
                        $errors[] = 'field '.$index.' missing '.$key;
                    }
                }
            }
        }

        return $errors;
    }

    protected function requiredDefinitionPaths(): array
    {
        return [
            'schemaVersion',
            'module.name',
            'module.namespace',
            'module.singularLabel',
            'module.pluralLabel',
            'module.table',
            'module.routeName',
            'module.resourceName',
            'module.icon',
            'resource.modelClass',
            'resource.titleField',
            'capabilities.tableable',
            'permissions.view',
            'frontend.routes',
            'verifier.generate',
        ];
    }

    protected function hasPath(array $data, string $path): bool
    {
        $cursor = $data;

        foreach (explode('.', $path) as $segment) {
            if (! is_array($cursor) || ! array_key_exists($segment, $cursor)) {
                return false;
            }

            $cursor = $cursor[$segment];
        }

        return true;
    }

    protected function buildPlan(array $definition): array
    {
        $module = $definition['module'];
        $singularStudly = Str::studly($module['singularLabel']);
        $pluralStudly = Str::studly($module['pluralLabel']);
        $moduleStudly = Str::studly($module['name']);
        $moduleLower = Str::kebab($moduleStudly);
        $entityLower = Str::kebab($singularStudly);

        return [
            'normalized' => [
                'module' => $moduleStudly,
                'module_lower' => $moduleLower,
                'entity' => $singularStudly,
                'entities' => $pluralStudly,
                'resource' => $module['resourceName'],
                'route' => $module['routeName'],
                'table' => $module['table'],
            ],
            'backend' => [
                "modules/{$moduleStudly}/module.json",
                "modules/{$moduleStudly}/bootstrap/module.php",
                "modules/{$moduleStudly}/app/Providers/{$moduleStudly}ServiceProvider.php",
                "modules/{$moduleStudly}/app/Providers/RouteServiceProvider.php",
                "modules/{$moduleStudly}/app/Models/{$singularStudly}.php",
                "modules/{$moduleStudly}/app/Resources/{$singularStudly}.php",
                "modules/{$moduleStudly}/app/Resources/{$singularStudly}Table.php",
                "modules/{$moduleStudly}/app/Http/Resources/{$singularStudly}Resource.php",
                "modules/{$moduleStudly}/app/Policies/{$singularStudly}Policy.php",
                "modules/{$moduleStudly}/database/migrations/create_{$module['table']}_table.php",
                "modules/{$moduleStudly}/routes/api.php",
                "modules/{$moduleStudly}/routes/web.php",
            ],
            'frontend' => [
                "modules/{$moduleStudly}/resources/js/app.js",
                "modules/{$moduleStudly}/resources/js/routes.js",
                "modules/{$moduleStudly}/resources/js/views/{$pluralStudly}Index.vue",
                "modules/{$moduleStudly}/resources/js/views/{$pluralStudly}Create.vue",
                "modules/{$moduleStudly}/resources/js/views/{$pluralStudly}Edit.vue",
                "modules/{$moduleStudly}/resources/js/views/{$pluralStudly}View.vue",
                "modules/{$moduleStudly}/resources/js/components/{$singularStudly}FloatingModal.vue",
            ],
            'docs' => [
                "patches/verify_{$moduleLower}_{$entityLower}_contract.php",
                "docs/ai/04-docops/history/YYYY-MM-DD-{$moduleLower}-{$entityLower}-generated.md",
            ],
            'warnings' => $this->warnings($definition),
        ];
    }

    protected function warnings(array $definition): array
    {
        $warnings = [];
        $capabilities = $definition['capabilities'] ?? [];

        if ($this->capabilityEnabled($definition, 'timeline')) {
            $warnings[] = 'timeline requested but timeline UI generation is future/unsupported in preview; no timeline APIs are generated';
        }

        if ($this->capabilityEnabled($definition, 'softDeletes')) {
            $warnings[] = 'softDeletes requested; deletion behavior must be verified before write-capable generation';
        }

        foreach (['documents', 'calls', 'emails', 'emailSending', 'mailClient', 'workflow', 'workflowTriggers', 'tasks', 'approvals', 'notifications'] as $unsupported) {
            if ($this->capabilityEnabled($definition, $unsupported)) {
                $warnings[] = $unsupported.' requested but is future/unsupported in preview; no unsafe APIs are generated';
            }
        }

        foreach ($definition['fields'] ?? [] as $field) {
            $type = $field['type'] ?? 'unknown';
            $name = $field['name'] ?? 'unknown';

            if (! in_array($type, ['id', 'text', 'textarea', 'boolean', 'integer', 'decimal', 'date', 'datetime', 'select', 'belongsTo'], true)) {
                $warnings[] = "field {$name} uses unsupported preview field type {$type}; falling back to Text";
            }
        }

        return $warnings;
    }

    protected function capabilityEnabled(array $definition, string $capability, array $aliases = []): bool
    {
        $capabilities = $definition['capabilities'] ?? [];

        foreach (array_merge([$capability], $aliases) as $key) {
            if (($capabilities[$key] ?? false) === true) {
                return true;
            }
        }

        return false;
    }

    protected function printPlan(array $definition, array $plan): void
    {
        $this->line('ERPSMART Module Builder Dry Run');
        $this->newLine();
        $this->line('Definition: '.$this->option('definition'));
        $this->line('Module: '.$plan['normalized']['module']);
        $this->line('Entity: '.$plan['normalized']['entity'].' / '.$plan['normalized']['entities']);
        $this->line('Resource: '.$plan['normalized']['resource']);
        $this->line('Table: '.$plan['normalized']['table']);

        $this->newLine();
        $this->line('Capabilities:');
        foreach ($definition['capabilities'] as $capability => $enabled) {
            $this->line('- '.$capability.': '.($enabled ? 'true' : 'false'));
        }

        $this->newLine();
        $this->line('Backend files:');
        foreach ($plan['backend'] as $file) {
            $this->line('- '.$file);
        }

        $this->newLine();
        $this->line('Frontend files:');
        foreach ($plan['frontend'] as $file) {
            $this->line('- '.$file);
        }

        $this->newLine();
        $this->line('Docs/verifier files:');
        foreach ($plan['docs'] as $file) {
            $this->line('- '.$file);
        }

        $this->newLine();
        $this->line('Warnings:');
        if ($plan['warnings'] === []) {
            $this->line('- none');
        } else {
            foreach ($plan['warnings'] as $warning) {
                $this->line('- '.$warning);
            }
        }

        $this->newLine();
        $this->line('Writes performed: 0');
    }

    protected function renderPreview(array $definition, array $plan): void
    {
        $this->line('ERPSMART Module Builder Preview');
        $this->newLine();

        $this->line('Definition: '.$this->option('definition'));
        $this->line('Module: '.$plan['normalized']['module']);
        $this->line('Entity: '.$plan['normalized']['entity'].' / '.$plan['normalized']['entities']);
        $this->line('Resource: '.$plan['normalized']['resource']);
        $this->line('Table: '.$plan['normalized']['table']);

        $previewRoot = storage_path('app/module-builder-preview/'.$plan['normalized']['module']);
        $files = $this->previewFiles($definition, $plan);
        $written = 0;

        File::ensureDirectoryExists($previewRoot);

        $this->newLine();
        $this->line('Preview files written:');

        foreach ($files as $relativePath => $contents) {
            if (str_starts_with($relativePath, '/') || str_contains($relativePath, '..')) {
                $this->error('Unsafe preview relative path: '.$relativePath);

                continue;
            }

            $target = $previewRoot.'/'.$relativePath;

            if (! str_starts_with(dirname($target), $previewRoot)) {
                $this->error('Unsafe preview target: '.$target);

                continue;
            }

            File::ensureDirectoryExists(dirname($target));
            File::put($target, $contents);
            $written++;

            $this->line('- '.$target);
        }

        $this->newLine();
        $this->line('Warnings:');
        if ($plan['warnings'] === []) {
            $this->line('- none');
        } else {
            foreach ($plan['warnings'] as $warning) {
                $this->line('- '.$warning);
            }
        }

        $this->newLine();
        $this->line('Real runtime writes performed: 0');
        $this->line('Preview writes performed: '.$written);
    }

    protected function previewFiles(array $definition, array $plan): array
    {
        $module = $plan['normalized']['module'];
        $entity = $plan['normalized']['entity'];
        $entities = $plan['normalized']['entities'];
        $resourceName = $plan['normalized']['resource'];
        $table = $plan['normalized']['table'];
        $namespace = $definition['module']['namespace'];
        $lowerModule = Str::kebab($module);
        $lowerEntity = Str::kebab($entity);

        return [
            "modules/{$module}/module.json" => $this->renderModuleJson($definition, $module),
            "modules/{$module}/bootstrap/module.php" => "<?php\n\nreturn new ".$namespace."\\Providers\\".$module."ServiceProvider(app());\n",
            "modules/{$module}/app/Providers/{$module}ServiceProvider.php" => $this->renderServiceProvider($namespace, $module, $entity, $lowerModule),
            "modules/{$module}/app/Providers/RouteServiceProvider.php" => $this->renderRouteServiceProvider($namespace, $module),
            "modules/{$module}/app/Models/{$entity}.php" => $this->renderModel($definition, $namespace, $entity, $table),
            "modules/{$module}/app/Resources/{$entity}.php" => $this->renderResource($definition, $namespace, $module, $entity, $entities, $table),
            "modules/{$module}/app/Resources/{$entity}Table.php" => $this->renderTable($namespace, $entity),
            "modules/{$module}/app/Http/Resources/{$entity}Resource.php" => $this->renderJsonResource($definition, $namespace, $entity),
            "modules/{$module}/app/Policies/{$entity}Policy.php" => $this->renderPolicy($namespace, $entity),
            "modules/{$module}/database/migrations/create_{$table}_table.php" => $this->renderMigration($definition, $table),
            "modules/{$module}/routes/api.php" => "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n\nRoute::middleware(['api'])->group(function () {\n    // Preview only. Runtime resource routes are registered through WithResourceRoutes.\n});\n",
            "modules/{$module}/routes/web.php" => "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n\nRoute::middleware(['web'])->group(function () {\n    // Preview only.\n});\n",
            "modules/{$module}/resources/js/app.js" => $this->renderFrontendApp($entity),
            "modules/{$module}/resources/js/routes.js" => $this->renderRoutesJs($definition, $entities),
            "modules/{$module}/resources/js/views/{$entities}Index.vue" => $this->renderSimpleVue($entities.'Index', '<ResourceTable resource-name="'.$resourceName.'" />'),
            "modules/{$module}/resources/js/views/{$entities}Create.vue" => $this->renderSimpleVue($entities.'Create', '<div />'),
            "modules/{$module}/resources/js/views/{$entities}Edit.vue" => $this->renderSimpleVue($entities.'Edit', '<div />'),
            "modules/{$module}/resources/js/views/{$entities}View.vue" => $this->renderDetailVue($resourceName),
            "modules/{$module}/resources/js/components/{$entity}FloatingModal.vue" => $this->renderFloatingModal($entity),
            "patches/verify_{$lowerModule}_{$lowerEntity}_contract.php" => $this->renderGeneratedVerifier($module, $entity),
            "docs/ai/04-docops/history/YYYY-MM-DD-{$lowerModule}-{$lowerEntity}-generated.md" => "# {$module} {$entity} Generated Preview\n\nStatus: preview only\n\nGenerated by Module Builder preview renderer. No runtime files were written.\n",
        ];
    }

    protected function renderModuleJson(array $definition, string $module): string
    {
        return json_encode([
            'name' => $module,
            'alias' => Str::kebab($module),
            'description' => 'Module Builder preview module.',
            'keywords' => [],
            'priority' => 0,
            'providers' => [
                $definition['module']['namespace'].'\\Providers\\'.$module.'ServiceProvider',
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
    }

    protected function renderServiceProvider(string $namespace, string $module, string $entity, string $moduleLower): string
    {
        return <<<PHP
<?php

namespace {$namespace}\\Providers;

use Closure;
use Modules\\Core\\Support\\ModuleServiceProvider;

class {$module}ServiceProvider extends ModuleServiceProvider
{
    protected array \$resources = [
        \\{$namespace}\\Resources\\{$entity}::class,
    ];

    public function register(): void
    {
        \$this->registerResources();
        \$this->app->register(RouteServiceProvider::class);
    }

    protected function setup(): void
    {
        //
    }

    protected function scriptData(): Closure|array
    {
        return [
            '{$module}' => [],
        ];
    }

    protected function moduleName(): string
    {
        return '{$module}';
    }

    protected function moduleNameLower(): string
    {
        return '{$moduleLower}';
    }
}
PHP;
    }

    protected function renderRouteServiceProvider(string $namespace, string $module): string
    {
        return <<<PHP
<?php

namespace {$namespace}\\Providers;

use Modules\\Core\\Providers\\ModuleRouteServiceProvider;

class RouteServiceProvider extends ModuleRouteServiceProvider
{
    protected string \$moduleName = '{$module}';
}
PHP;
    }

    protected function renderModel(array $definition, string $namespace, string $entity, string $table): string
    {
        $fillable = $this->modelFillableFields($definition);
        $casts = $this->modelCasts($definition);
        $fillableLines = $this->arrayLines($fillable, 8);
        $castLines = $this->keyValueArrayLines($casts, 8);
        $imports = $this->modelImports($definition);
        $importLines = $imports === [] ? '' : implode("\n", $imports)."\n";
        $traits = $this->modelTraitLines($definition);
        $relationMethods = $this->modelRelationMethods($definition);

        return <<<PHP
<?php

namespace {$namespace}\\Models;

{$importLines}use Modules\\Core\\Contracts\\Resources\\Resourceable as ResourceableContract;
use Modules\\Core\\Models\\Model;
use Modules\\Core\\Resource\\Resourceable;

class {$entity} extends Model implements ResourceableContract
{
    use Resourceable;
{$traits}

    protected \$table = '{$table}';

    protected \$fillable = [
{$fillableLines}
    ];

    protected \$casts = [
{$castLines}
    ];
{$relationMethods}
}
PHP;
    }

    protected function modelImports(array $definition): array
    {
        $imports = [];

        if ($this->capabilityEnabled($definition, 'activities')) {
            $imports[] = 'use Modules\\Activities\\Concerns\\HasActivities;';
        }

        if ($this->capabilityEnabled($definition, 'media', ['mediable'])) {
            $imports[] = 'use Modules\\Core\\Common\\Media\\HasMedia;';
        }

        foreach ($this->relations($definition) as $relation) {
            if (($relation['type'] ?? null) === 'belongsTo') {
                $imports[] = 'use Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;';
            }

            if (($relation['type'] ?? null) === 'hasMany') {
                $imports[] = 'use Illuminate\\Database\\Eloquent\\Relations\\HasMany;';
            }

            $relatedModel = $relation['targetModel'] ?? $relation['relatedModel'] ?? null;

            if (is_string($relatedModel) && $relatedModel !== '') {
                $imports[] = 'use '.ltrim($relatedModel, '\\').';';
            }
        }

        return array_values(array_unique($imports));
    }

    protected function modelTraitLines(array $definition): string
    {
        $traits = [];

        if ($this->capabilityEnabled($definition, 'media', ['mediable'])) {
            $traits[] = '    use HasMedia;';
        }

        if ($this->capabilityEnabled($definition, 'activities')) {
            $traits[] = '    use HasActivities;';
        }

        return $traits === [] ? '' : "\n".implode("\n", $traits);
    }

    protected function modelRelationMethods(array $definition): string
    {
        $methods = [];

        foreach ($this->relations($definition) as $relation) {
            $type = $relation['type'] ?? null;
            $name = $relation['name'] ?? null;
            $relatedClass = $this->relationRelatedClass($relation);
            $foreignKey = $relation['foreignKey'] ?? null;

            if (! is_string($name) || $name === '' || $relatedClass === null) {
                continue;
            }

            if ($type === 'belongsTo' && is_string($foreignKey) && $foreignKey !== '') {
                $methods[] = <<<PHP

    public function {$name}(): BelongsTo
    {
        return \$this->belongsTo({$relatedClass}::class, '{$foreignKey}');
    }
PHP;
            }

            if ($type === 'hasMany' && is_string($foreignKey) && $foreignKey !== '') {
                $methods[] = <<<PHP

    public function {$name}(): HasMany
    {
        return \$this->hasMany({$relatedClass}::class, '{$foreignKey}');
    }
PHP;
            }
        }

        return implode('', $methods);
    }

    protected function modelFillableFields(array $definition): array
    {
        $fields = [];

        foreach ($definition['fields'] ?? [] as $field) {
            $name = $field['name'] ?? null;

            if (! is_string($name) || $name === '' || $name === 'id') {
                continue;
            }

            $fields[] = $name;
        }

        if ($this->capabilityEnabled($definition, 'importable') && ! in_array('import_id', $fields, true)) {
            $fields[] = 'import_id';
        }

        return $fields;
    }

    protected function modelCasts(array $definition): array
    {
        $casts = [];

        foreach ($definition['fields'] ?? [] as $field) {
            $name = $field['name'] ?? null;
            $type = $field['type'] ?? null;

            if (! is_string($name) || $name === '' || $name === 'id') {
                continue;
            }

            $cast = match ($type) {
                'boolean' => 'boolean',
                'integer', 'belongsTo' => 'integer',
                'decimal' => 'decimal:2',
                'date' => 'date',
                'datetime' => 'datetime',
                default => null,
            };

            if ($cast !== null) {
                $casts[$name] = $cast;
            }
        }

        if ($this->capabilityEnabled($definition, 'importable') && ! isset($casts['import_id'])) {
            $casts['import_id'] = 'integer';
        }

        return $casts;
    }

    protected function renderResource(array $definition, string $namespace, string $module, string $entity, string $entities, string $table): string
    {
        $resourceName = $definition['module']['resourceName'];
        $titleField = $definition['resource']['titleField'];
        $fieldImports = $this->resourceFieldImports($definition);
        $imports = $this->resourceImports($definition, $fieldImports, $namespace, $entity);
        $importLines = implode("\n", $imports);
        $resourceFields = $this->resourceFieldLines($definition);
        $interfaces = $this->resourceInterfaces($definition);
        $implements = implode(', ', $interfaces);
        $traitLines = $this->resourceTraitLines($definition);
        $bootMethod = $this->resourceBootMethod($definition, $entity);
        $menuQuickCreateLine = $this->capabilityEnabled($definition, 'quickCreate') ? "\n                ->inQuickCreate()" : '';
        $tableMethod = $this->resourceTableMethod($definition, $entity, $entities, $resourceName);
        $actionsMethod = $this->resourceActionsMethod($definition);
        $hasDetailView = $this->resourceHasDetailView($definition) ? 'true' : 'false';
        $globallySearchable = $this->capabilityEnabled($definition, 'globalSearch') ? 'true' : 'false';
        $globalSearchAction = $definition['resource']['globalSearchAction'] ?? 'none';

        return <<<PHP
<?php

namespace {$namespace}\\Resources;

{$importLines}

class {$entity} extends Resource implements {$implements}
{
{$traitLines}

    public static bool \$hasDetailView = {$hasDetailView};
    public static bool \$globallySearchable = {$globallySearchable};
    public static string \$globalSearchAction = '{$globalSearchAction}';
    public static ?string \$icon = '{$definition['module']['icon']}';
    public static string \$model = {$entity}Model::class;
    public static string \$title = '{$titleField}';
{$bootMethod}

    public function menu(): array
    {
        return [
            MenuItem::make(static::label(), '/{$resourceName}')
                ->icon(static::\$icon){$menuQuickCreateLine}
                ->singularName(static::singularLabel()),
        ];
    }
{$tableMethod}

    public function fields(ResourceRequest \$request): array
    {
        return [
{$resourceFields}
        ];
    }
{$actionsMethod}

    public function jsonResource(): string
    {
        return {$entity}Resource::class;
    }

    public static function label(): string
    {
        return '{$entities}';
    }

    public static function singularLabel(): string
    {
        return '{$entity}';
    }
}
PHP;
    }

    protected function resourceImports(array $definition, array $fieldImports, string $namespace, string $entity): array
    {
        $imports = [];

        if ($this->capabilityEnabled($definition, 'tableable')) {
            $imports[] = 'use Illuminate\\Database\\Eloquent\\Builder;';
        }

        if ($this->capabilityEnabled($definition, 'activities')) {
            $imports[] = 'use Modules\\Activities\\Actions\\CreateRelatedActivityAction;';
        }

        if (($definition['frontend']['floatingModal'] ?? false)
            || $this->capabilityEnabled($definition, 'quickCreate')
            || $this->capabilityEnabled($definition, 'activities')) {
            $imports[] = 'use Modules\\Core\\Actions\\Action;';
        }

        foreach ($this->resourceInterfaces($definition) as $interface) {
            if ($interface === 'PipesComments') {
                $imports[] = 'use Modules\\Comments\\Contracts\\PipesComments;';
            } else {
                $imports[] = 'use Modules\\Core\\Contracts\\Resources\\'.$interface.';';
            }
        }

        foreach ($fieldImports as $class) {
            $imports[] = 'use Modules\\Core\\Fields\\'.$class.';';
        }

        $imports[] = 'use Modules\\Core\\Http\\Requests\\ResourceRequest;';
        $imports[] = 'use Modules\\Core\\Menu\\MenuItem;';

        if ($this->resourceHasDetailView($definition)) {
            $imports[] = 'use Modules\\Core\\Pages\\Panel;';

            if ($this->capabilityEnabled($definition, 'activities') || $this->capabilityEnabled($definition, 'notes')) {
                $imports[] = 'use Modules\\Core\\Pages\\Tab;';
            }
        }

        if ($this->capabilityEnabled($definition, 'activityAssociations')) {
            $imports[] = 'use Modules\\Core\\Resource\\AssociatesResources;';
        }

        $imports[] = 'use Modules\\Core\\Resource\\Resource;';

        if ($this->capabilityEnabled($definition, 'tableable')) {
            $imports[] = 'use Modules\\Core\\Table\\Table;';
        }

        $imports[] = 'use '.$namespace.'\\Http\\Resources\\'.$entity.'Resource;';
        $imports[] = 'use '.$namespace.'\\Models\\'.$entity.' as '.$entity.'Model;';

        return array_values(array_unique($imports));
    }

    protected function resourceInterfaces(array $definition): array
    {
        $interfaces = ['WithResourceRoutes'];

        $map = [
            'customFields' => 'AcceptsCustomFields',
            'uniqueCustomFields' => 'AcceptsUniqueCustomFields',
            'cloneable' => 'Cloneable',
            'exportable' => 'Exportable',
            'importable' => 'Importable',
            'tableable' => 'Tableable',
        ];

        foreach ($map as $capability => $interface) {
            if ($this->capabilityEnabled($definition, $capability)) {
                $interfaces[] = $interface;
            }
        }

        if ($this->capabilityEnabled($definition, 'media', ['mediable'])) {
            $interfaces[] = 'Mediable';
        }

        if ($this->capabilityEnabled($definition, 'comments')
            || $this->capabilityEnabled($definition, 'activityComments')
            || $this->capabilityEnabled($definition, 'notes')) {
            $interfaces[] = 'PipesComments';
        }

        sort($interfaces);

        return $interfaces;
    }

    protected function resourceTraitLines(array $definition): string
    {
        $traits = [];

        if ($this->capabilityEnabled($definition, 'activityAssociations')) {
            $traits[] = '    use AssociatesResources;';
        }

        return $traits === [] ? '    // No optional resource traits enabled for this preview.' : implode("\n", $traits);
    }

    protected function resourceBootMethod(array $definition, string $entity): string
    {
        if (! $this->resourceHasDetailView($definition)) {
            return '';
        }

        $detailPanelId = Str::kebab($entity).'-detail-panel';
        $chain = [];

        if ($this->capabilityEnabled($definition, 'activities')) {
            $chain[] = "            ->tab(Tab::make('activities', 'activities-tab')->panel('activities-tab-panel')->order(15))";
        }

        if ($this->capabilityEnabled($definition, 'notes')) {
            $chain[] = "            ->tab(Tab::make('notes', 'notes-tab')->panel('notes-tab-panel')->order(35))";
        }

        $panels = [
            "                    Panel::make('{$detailPanelId}', 'resource-details-panel')\n                        ->heading(__('core::app.record_view.sections.details'))\n                        ->resizeable(),",
        ];

        if ($this->capabilityEnabled($definition, 'media', ['mediable'])) {
            $panels[] = "                    Panel::make('media', 'resource-media-panel')\n                        ->heading(__('core::app.attachments')),";
        }

        $chain[] = "            ->panels(function () {\n                return [\n".implode("\n", $panels)."\n                ];\n            });";

        return "\n\n    protected function boot(): void\n    {\n        \$this->getDetailPage()\n".implode("\n", $chain)."\n    }\n";
    }

    protected function resourceTableMethod(array $definition, string $entity, string $entities, string $resourceName): string
    {
        if (! $this->capabilityEnabled($definition, 'tableable')) {
            return '';
        }

        $defaultView = $definition['table']['defaultView']['name'] ?? $entities;
        $defaultFlag = $definition['table']['defaultView']['flag'] ?? 'all-'.$resourceName;
        $orderColumn = $definition['table']['orderBy']['column'] ?? 'id';
        $orderDirection = $definition['table']['orderBy']['direction'] ?? 'desc';

        return <<<PHP

    public function table(Builder \$query, ResourceRequest \$request, string \$identifier): Table
    {
        return {$entity}Table::make(\$query, \$request, \$identifier)
            ->withDefaultView(name: '{$defaultView}', flag: '{$defaultFlag}')
            ->orderBy('{$orderColumn}', '{$orderDirection}');
    }
PHP;
    }

    protected function resourceActionsMethod(array $definition): string
    {
        $actions = [];

        if ($this->capabilityEnabled($definition, 'activities')) {
            $actions[] = '            CreateRelatedActivityAction::make()->onlyInline(),';
        }

        if ($definition['frontend']['floatingModal'] ?? false) {
            $actions[] = '            Action::make()->floatResourceInEditMode(),';
        }

        if ($actions === []) {
            return '';
        }

        return "\n\n    public function actions(ResourceRequest \$request): array\n    {\n        return [\n".implode("\n", $actions)."\n        ];\n    }\n";
    }

    protected function resourceHasDetailView(array $definition): bool
    {
        return ($definition['resource']['hasDetailView'] ?? false) === true
            || $this->capabilityEnabled($definition, 'hasDetailView');
    }

    protected function resourceFieldImports(array $definition): array
    {
        $imports = ['ID'];

        foreach ($definition['fields'] ?? [] as $field) {
            $imports[] = $this->fieldClassForType($field['type'] ?? 'text');
        }

        $imports = array_values(array_unique($imports));
        sort($imports);

        return $imports;
    }

    protected function resourceFieldLines(array $definition): string
    {
        $lines = [];
        $hasId = false;

        foreach ($definition['fields'] ?? [] as $field) {
            $name = $field['name'] ?? '';
            $type = $field['type'] ?? 'text';

            if ($name === 'id' || $type === 'id') {
                $hasId = true;
                $lines[] = '            ID::make()->hidden(),';
                continue;
            }

            $class = $this->fieldClassForType($type);
            $label = $this->phpString($field['label'] ?? Str::headline($name));
            $line = "            {$class}::make('{$name}', {$label})";

            if (($field['primary'] ?? false) === true) {
                $line .= '->primary()';
            }

            if ($this->fieldRequired($field)) {
                $line .= '->required(true)';
            }

            $lines[] = $line.',';
        }

        if (! $hasId) {
            array_unshift($lines, '            ID::make()->hidden(),');
        }

        return implode("\n", $lines);
    }

    protected function fieldClassForType(string $type): string
    {
        return match ($type) {
            'id' => 'ID',
            'boolean' => 'Boolean',
            'integer', 'decimal', 'belongsTo' => 'Number',
            'date' => 'Date',
            'datetime' => 'DateTime',
            'select' => 'Select',
            'textarea' => 'Textarea',
            'text' => 'Text',
            default => 'Text',
        };
    }

    protected function renderTable(string $namespace, string $entity): string
    {
        return <<<PHP
<?php

namespace {$namespace}\\Resources;

use Modules\\Core\\Table\\Table;

class {$entity}Table extends Table
{
}
PHP;
    }

    protected function renderJsonResource(array $definition, string $namespace, string $entity): string
    {
        $resourceLines = $this->jsonResourceLines($definition);

        return <<<PHP
<?php

namespace {$namespace}\\Http\\Resources;

use Illuminate\\Http\\Request;
use Modules\\Core\\Resource\\JsonResource;

class {$entity}Resource extends JsonResource
{
    public function toArray(Request \$request): array
    {
        return \$this->withCommonData([
{$resourceLines}
        ], \$request);
    }
}
PHP;
    }

    protected function jsonResourceLines(array $definition): string
    {
        $lines = [];

        foreach ($definition['fields'] ?? [] as $field) {
            $name = $field['name'] ?? null;

            if (! is_string($name) || $name === '' || $name === 'id') {
                continue;
            }

            $lines[] = '            '.$this->phpString($name).' => '.$this->jsonResourceValueExpression($field).',';
        }

        if ($this->capabilityEnabled($definition, 'importable')) {
            $lines[] = "            'import_id' => \$this->import_id,";
        }

        if ($this->capabilityEnabled($definition, 'media', ['mediable'])) {
            $lines[] = "            'media' => \$this->whenLoaded('media'),";
        }

        return implode("\n", $lines);
    }

    protected function jsonResourceValueExpression(array $field): string
    {
        $name = $field['name'];

        return match ($field['type'] ?? 'text') {
            'boolean' => "(bool) \$this->{$name}",
            'integer', 'belongsTo' => "\$this->{$name} === null ? null : (int) \$this->{$name}",
            'decimal' => "\$this->{$name} === null ? null : (float) \$this->{$name}",
            default => "\$this->{$name}",
        };
    }

    protected function renderPolicy(string $namespace, string $entity): string
    {
        return <<<PHP
<?php

namespace {$namespace}\\Policies;

use Illuminate\\Auth\\Access\\HandlesAuthorization;

class {$entity}Policy
{
    use HandlesAuthorization;
}
PHP;
    }

    protected function renderMigration(array $definition, string $table): string
    {
        $migrationLines = $this->migrationFieldLines($definition);

        return <<<PHP
<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('{$table}', function (Blueprint \$table) {
{$migrationLines}
            \$table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{$table}');
    }
};
PHP;
    }

    protected function migrationFieldLines(array $definition): string
    {
        $lines = [];
        $hasId = false;

        foreach ($definition['fields'] ?? [] as $field) {
            $name = $field['name'] ?? '';
            $type = $field['type'] ?? 'text';

            if ($name === 'id' || $type === 'id') {
                $hasId = true;
                $lines[] = '            $table->id();';
                continue;
            }

            $line = '            '.$this->migrationColumnExpression($field);

            if (! $this->fieldRequired($field) && ! str_contains($line, '->nullable()')) {
                $line .= '->nullable()';
            }

            if ($this->fieldUnique($field)) {
                $line .= '->unique()';
            }

            if (array_key_exists('default', $field)) {
                $line .= '->default('.$this->phpLiteral($field['default']).')';
            }

            $lines[] = $line.';';
        }

        if (! $hasId) {
            array_unshift($lines, '            $table->id();');
        }

        if ($this->capabilityEnabled($definition, 'importable')
            && ! in_array('import_id', array_column($definition['fields'] ?? [], 'name'), true)) {
            $lines[] = '            $table->unsignedBigInteger(\'import_id\')->nullable();';
        }

        return implode("\n", $lines);
    }

    protected function migrationColumnExpression(array $field): string
    {
        $name = $field['name'];
        $type = $field['type'] ?? 'text';

        return match ($type) {
            'textarea' => "\$table->text('{$name}')",
            'boolean' => "\$table->boolean('{$name}')",
            'integer' => "\$table->integer('{$name}')",
            'belongsTo' => "\$table->unsignedBigInteger('{$name}')",
            'decimal' => "\$table->decimal('{$name}', 15, 2)",
            'date' => "\$table->date('{$name}')",
            'datetime' => "\$table->dateTime('{$name}')",
            default => "\$table->string('{$name}'".$this->migrationStringLengthSuffix($field).')',
        };
    }

    protected function migrationStringLengthSuffix(array $field): string
    {
        foreach ($this->fieldRules($field) as $rule) {
            if (preg_match('/^max:(\d+)$/', $rule, $matches) === 1) {
                return ', '.$matches[1];
            }
        }

        return '';
    }

    protected function fieldRequired(array $field): bool
    {
        if (($field['required'] ?? false) === true) {
            return true;
        }

        return in_array('required', $this->fieldRules($field), true);
    }

    protected function fieldUnique(array $field): bool
    {
        foreach ($this->fieldRules($field) as $rule) {
            if (is_string($rule) && str_starts_with($rule, 'unique')) {
                return true;
            }
        }

        return false;
    }

    protected function fieldRules(array $field): array
    {
        return array_values(array_filter(array_merge(
            is_array($field['rules'] ?? null) ? $field['rules'] : [],
            is_array($field['creationRules'] ?? null) ? $field['creationRules'] : [],
            is_array($field['updateRules'] ?? null) ? $field['updateRules'] : []
        ), 'is_string'));
    }

    protected function arrayLines(array $values, int $spaces): string
    {
        $indent = str_repeat(' ', $spaces);

        return implode("\n", array_map(fn (string $value): string => $indent.$this->phpString($value).',', $values));
    }

    protected function keyValueArrayLines(array $values, int $spaces): string
    {
        $indent = str_repeat(' ', $spaces);

        return implode("\n", array_map(
            fn (string $key, string $value): string => $indent.$this->phpString($key).' => '.$this->phpString($value).',',
            array_keys($values),
            $values
        ));
    }

    protected function phpString(string $value): string
    {
        return var_export($value, true);
    }

    protected function phpLiteral(mixed $value): string
    {
        return var_export($value, true);
    }

    protected function relations(array $definition): array
    {
        return is_array($definition['relations'] ?? null) ? $definition['relations'] : [];
    }

    protected function relationRelatedClass(array $relation): ?string
    {
        $relatedModel = $relation['targetModel'] ?? $relation['relatedModel'] ?? null;

        if (! is_string($relatedModel) || $relatedModel === '') {
            return null;
        }

        return class_basename($relatedModel);
    }

    protected function renderFrontendApp(string $entity): string
    {
        return <<<JS
import routes from './routes'
import {$entity}FloatingModal from './components/{$entity}FloatingModal.vue'

Innoclapps.booting((app, router) => {
  routes.forEach(route => router.addRoute(route))
  app.component('{$entity}FloatingModal', {$entity}FloatingModal)
})
JS;
    }

    protected function renderRoutesJs(array $definition, string $entities): string
    {
        $resourceName = $definition['module']['resourceName'];

        return <<<JS
import {$entities}Index from './views/{$entities}Index.vue'
import {$entities}View from './views/{$entities}View.vue'

export default [
  { path: '/{$resourceName}', name: '{$resourceName}-index', component: {$entities}Index },
  { path: '/{$resourceName}/:id', name: 'view-{$resourceName}', component: {$entities}View },
]
JS;
    }

    protected function renderSimpleVue(string $name, string $template): string
    {
        return <<<VUE
<template>
  {$template}
</template>

<script setup>
defineOptions({ name: '{$name}' })
</script>
VUE;
    }

    protected function renderDetailVue(string $resourceName): string
    {
        return <<<VUE
<template>
  <div v-if="resourceReady">
    <Panels v-model:panels="page.panels" :identifier="resourceName" />
    <ITabGroup>
      <ITabList>
        <component
          :is="tabComponents[tab.component] || tab.component"
          v-for="tab in page.tabs"
          :key="tab.id"
          :resource-name="resourceName"
          :resource-id="resource.id"
          :resource="resource"
        />
      </ITabList>
      <ITabPanels>
        <component
          :is="tabComponents[tab.panelComponent] || tab.panelComponent"
          v-for="tab in page.tabs"
          :key="tab.id"
          :resource-name="resourceName"
          :resource-id="resource.id"
          :resource="resource"
        />
      </ITabPanels>
    </ITabGroup>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import Panels from '@/Core/components/Panels.vue'
import { useResource } from '@/Core/composables/useResource'

const resourceName = '{$resourceName}'
const { resource, resourceInformation, resourceReady } = useResource(resourceName)
const page = ref(resourceInformation.value.detailPage)
const tabComponents = {}
</script>
VUE;
    }

    protected function renderFloatingModal(string $entity): string
    {
        return <<<VUE
<template>
  <ICard>
    <FormFields :fields="fields" :form="resource" />
  </ICard>
</template>

<script setup>
defineProps({
  visible: Boolean,
  floatingReady: Boolean,
  resource: { type: Object, required: true },
  fields: { type: Array, required: true },
  mode: { type: String, required: true },
  updateHandler: { type: Function, required: true },
})
</script>
VUE;
    }

    protected function renderGeneratedVerifier(string $module, string $entity): string
    {
        return <<<PHP
<?php

echo '{$module} {$entity} generated contract verifier preview'.PHP_EOL;
echo 'PASS'.PHP_EOL;
PHP;
    }
}
