<?php

namespace App\Http\Controllers\Builder;

use App\Http\Requests\Builder\StoreBuilderDefinitionRequest;
use App\Http\Requests\Builder\UpdateBuilderDefinitionRequest;
use App\Models\BuilderDefinition;
use App\Services\Builder\BuilderDefinitionValidator;
use App\Services\Builder\BuilderDefinitionVersionService;
use App\Services\Builder\BuilderPreviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Modules\Core\Http\Controllers\ApiController;

class BuilderDefinitionController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $definitions = BuilderDefinition::query()
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return $this->response($definitions);
    }

    public function store(
        StoreBuilderDefinitionRequest $request,
        BuilderDefinitionVersionService $versions
    ): JsonResponse {
        $definitionJson = $request->array('definition_json');
        $userId = $request->user()?->getKey();

        $definition = BuilderDefinition::create(array_merge(
            $this->definitionAttributes($definitionJson),
            [
                'name' => (string) $request->string('name'),
                'slug' => $this->uniqueSlug((string) $request->string('name')),
                'status' => BuilderDefinition::STATUS_DRAFT,
                'schema_version' => (int) ($definitionJson['schemaVersion'] ?? 1),
                'definition_json' => $definitionJson,
                'checksum' => $this->checksum($definitionJson),
                'created_by' => $userId,
                'updated_by' => $userId,
            ]
        ));

        $versions->createVersion($definition, $userId);

        return $this->response($definition->load('versions'), JsonResponse::HTTP_CREATED);
    }

    public function show(BuilderDefinition $builderDefinition): JsonResponse
    {
        return $this->response(
            $builderDefinition->load(['versions' => fn ($query) => $query->latest('version'), 'previewRuns' => fn ($query) => $query->latest()])
        );
    }

    public function update(
        BuilderDefinition $builderDefinition,
        UpdateBuilderDefinitionRequest $request,
        BuilderDefinitionVersionService $versions
    ): JsonResponse {
        $definitionJson = $request->has('definition_json')
            ? $request->array('definition_json')
            : $builderDefinition->definition_json;

        $previousChecksum = $builderDefinition->checksum;
        $userId = $request->user()?->getKey();

        $builderDefinition->fill(array_merge(
            $this->definitionAttributes($definitionJson),
            [
                'name' => $request->input('name', $builderDefinition->name),
                'status' => BuilderDefinition::STATUS_DRAFT,
                'schema_version' => (int) ($definitionJson['schemaVersion'] ?? 1),
                'definition_json' => $definitionJson,
                'checksum' => $this->checksum($definitionJson),
                'last_validation_report_json' => null,
                'last_preview_manifest_json' => null,
                'updated_by' => $userId,
            ]
        ))->save();

        $versions->createVersion($builderDefinition, $userId, [
            'previous_checksum' => $previousChecksum,
            'next_checksum' => $builderDefinition->checksum,
        ]);

        return $this->response($builderDefinition->fresh('versions'));
    }

    public function validateDefinition(
        BuilderDefinition $builderDefinition,
        BuilderDefinitionValidator $validator,
        BuilderDefinitionVersionService $versions
    ): JsonResponse {
        $builderDefinition->transitionTo(BuilderDefinition::STATUS_VALIDATING);

        $report = $validator->validate($builderDefinition->definition_json);
        $builderDefinition->transitionTo(
            $report['valid'] ? BuilderDefinition::STATUS_VALIDATED : BuilderDefinition::STATUS_VALIDATION_FAILED,
            ['last_validation_report_json' => $report]
        );

        $versions->updateLatestValidationReport($builderDefinition, $report);

        return $this->response([
            'definition' => $builderDefinition->fresh(),
            'report' => $report,
        ]);
    }

    public function preview(
        BuilderDefinition $builderDefinition,
        BuilderDefinitionValidator $validator,
        BuilderDefinitionVersionService $versions,
        BuilderPreviewService $preview
    ): JsonResponse {
        $report = $validator->validate($builderDefinition->definition_json);

        if (! $report['valid']) {
            $builderDefinition->transitionTo(BuilderDefinition::STATUS_VALIDATION_FAILED, [
                'last_validation_report_json' => $report,
            ]);
            $versions->updateLatestValidationReport($builderDefinition, $report);

            return $this->response([
                'message' => 'Builder definition validation failed. Preview was not rendered.',
                'report' => $report,
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $builderDefinition->fill([
            'status' => BuilderDefinition::STATUS_VALIDATED,
            'last_validation_report_json' => $report,
        ])->save();
        $versions->updateLatestValidationReport($builderDefinition, $report);

        $run = $preview->preview($builderDefinition, request()->user()?->getKey());

        if ($run->manifest_json) {
            $versions->updateLatestPreviewManifest($builderDefinition->fresh(), $run->manifest_json);
        }

        return $this->response([
            'definition' => $builderDefinition->fresh(),
            'preview_run' => $run->fresh(),
        ], $run->status === 'previewed' ? JsonResponse::HTTP_OK : JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
    }

    protected function definitionAttributes(array $definitionJson): array
    {
        return [
            'module_name' => Arr::get($definitionJson, 'module.name'),
            'entity_name' => Arr::get($definitionJson, 'module.singularLabel'),
            'resource_name' => Arr::get($definitionJson, 'module.resourceName'),
        ];
    }

    protected function checksum(array $definitionJson): string
    {
        return hash('sha256', json_encode($definitionJson, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'builder-definition';
        $slug = $base;
        $index = 2;

        while (BuilderDefinition::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$index;
            $index++;
        }

        return $slug;
    }
}
