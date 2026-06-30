<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ErpsmartMakeModuleCommand extends Command
{
    protected $signature = 'erpsmart:make-module
        {--definition= : Path to the module definition JSON file}
        {--dry-run : Validate and print planned files without writing anything}';

    protected $description = 'Dry-run the ERPSMART Module Builder MVP without writing generated module files.';

    public function handle(): int
    {
        if (! $this->option('dry-run')) {
            $this->error('Refusing to run without --dry-run. This MVP command is dry-run only.');

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

        $this->printPlan($definition, $plan);

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

        if (($capabilities['timeline'] ?? false) === true) {
            $warnings[] = 'timeline requested but timeline UI generation is out of MVP dry-run implementation scope';
        }

        if (($capabilities['softDeletes'] ?? false) === true) {
            $warnings[] = 'softDeletes requested; deletion behavior must be verified before write-capable generation';
        }

        foreach (['documents', 'calls', 'emails', 'mailClient', 'workflowTriggers'] as $unsupported) {
            if (($capabilities[$unsupported] ?? false) === true) {
                $warnings[] = $unsupported.' requested but is out of MVP scope';
            }
        }

        return $warnings;
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
}
