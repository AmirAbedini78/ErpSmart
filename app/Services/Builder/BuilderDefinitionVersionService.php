<?php

namespace App\Services\Builder;

use App\Models\BuilderDefinition;
use App\Models\BuilderDefinitionVersion;

class BuilderDefinitionVersionService
{
    public function createVersion(BuilderDefinition $definition, ?int $userId = null, ?array $diff = null): BuilderDefinitionVersion
    {
        $version = ((int) $definition->versions()->max('version')) + 1;

        return $definition->versions()->create([
            'version' => $version,
            'status' => $definition->status,
            'definition_json' => $definition->definition_json,
            'checksum' => $definition->checksum,
            'validation_report_json' => $definition->last_validation_report_json,
            'preview_manifest_json' => $definition->last_preview_manifest_json,
            'diff_json' => $diff,
            'created_by' => $userId,
        ]);
    }

    public function updateLatestValidationReport(BuilderDefinition $definition, array $report): void
    {
        $latest = $definition->versions()->latest('version')->first();

        if ($latest) {
            $latest->fill([
                'status' => $definition->status,
                'validation_report_json' => $report,
            ])->save();
        }
    }

    public function updateLatestPreviewManifest(BuilderDefinition $definition, array $manifest): void
    {
        $latest = $definition->versions()->latest('version')->first();

        if ($latest) {
            $latest->fill([
                'status' => $definition->status,
                'preview_manifest_json' => $manifest,
            ])->save();
        }
    }
}
