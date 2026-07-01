<?php

namespace App\Services\Builder;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

class BuilderDefinitionValidator
{
    public function validate(mixed $definition): array
    {
        $errors = [];
        $warnings = [];

        if (! is_array($definition)) {
            return [
                'valid' => false,
                'errors' => ['definition must be a JSON object/array'],
                'warnings' => [],
            ];
        }

        foreach ($this->requiredPaths() as $path) {
            if (! Arr::has($definition, $path)) {
                $errors[] = 'missing '.$path;
            }
        }

        if (($definition['schemaVersion'] ?? null) !== 1) {
            $errors[] = 'schemaVersion must be 1';
        }

        if (isset($definition['fields']) && (! is_array($definition['fields']) || count($definition['fields']) === 0)) {
            $errors[] = 'fields must be a non-empty array';
        }

        foreach (($definition['fields'] ?? []) as $index => $field) {
            if (! is_array($field)) {
                $errors[] = 'field '.$index.' must be an object';

                continue;
            }

            foreach (['name', 'type', 'label', 'rules', 'visibility'] as $key) {
                if (! array_key_exists($key, $field)) {
                    $errors[] = 'field '.$index.' missing '.$key;
                }
            }
        }

        if (Arr::has($definition, 'packs')) {
            $errors[] = 'ERP packs/presets are not supported by the raw Builder definition';
        }

        $schema = $this->schema();
        if ($schema === null) {
            $warnings[] = 'module-builder-mvp-schema.json could not be loaded; structural validation only';
        }

        foreach ($this->warningOnlyCapabilities() as $capability) {
            if (Arr::get($definition, 'capabilities.'.$capability) === true) {
                $warnings[] = $capability.' requested but is future/unsupported in preview; no unsafe APIs are generated';
            }
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    protected function requiredPaths(): array
    {
        return [
            'schemaVersion',
            'module.name',
            'module.namespace',
            'module.table',
            'module.routeName',
            'module.resourceName',
            'resource.modelClass',
            'resource.titleField',
            'fields',
            'capabilities',
            'permissions',
            'frontend',
            'verifier.generate',
        ];
    }

    protected function warningOnlyCapabilities(): array
    {
        return [
            'documents',
            'calls',
            'emails',
            'emailSending',
            'tasks',
            'workflow',
            'approvals',
            'notifications',
            'timeline',
            'softDeletes',
            'formLayout',
            'stepperForm',
            'sections',
            'conditionalVisibility',
        ];
    }

    protected function schema(): ?array
    {
        $path = base_path('docs/ai/05-rag/contracts/module-builder-mvp-schema.json');

        if (! File::isFile($path)) {
            return null;
        }

        $schema = json_decode((string) File::get($path), true);

        return is_array($schema) ? $schema : null;
    }
}
