<?php

namespace App\Services\Builder;

use App\Models\BuilderDefinition;
use App\Models\BuilderPreviewRun;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

class BuilderPreviewService
{
    public function preview(BuilderDefinition $definition, ?int $userId = null): BuilderPreviewRun
    {
        $run = $definition->previewRuns()->create([
            'status' => 'previewing',
            'started_at' => now(),
            'created_by' => $userId,
        ]);

        $definition->transitionTo(BuilderDefinition::STATUS_PREVIEWING);

        try {
            $relativeDefinitionPath = $this->writeTemporaryDefinition($definition);

            $exitCode = Artisan::call('erpsmart:make-module', [
                '--definition' => $relativeDefinitionPath,
                '--preview' => true,
            ]);

            $output = Artisan::output();
            $manifest = $this->manifestFromOutput($definition, $output);
            $status = $exitCode === 0 ? 'previewed' : 'preview_failed';

            $run->fill([
                'status' => $status,
                'preview_path' => $manifest['preview_path'] ?? null,
                'manifest_json' => $manifest,
                'output_text' => $output,
                'error_text' => $exitCode === 0 ? null : $output,
                'finished_at' => now(),
            ])->save();

            $definition->transitionTo(
                $exitCode === 0 ? BuilderDefinition::STATUS_PREVIEWED : BuilderDefinition::STATUS_PREVIEW_FAILED,
                ['last_preview_manifest_json' => $manifest]
            );

            return $run;
        } catch (Throwable $e) {
            $run->fill([
                'status' => 'preview_failed',
                'error_text' => $e->getMessage(),
                'finished_at' => now(),
            ])->save();

            $definition->transitionTo(BuilderDefinition::STATUS_PREVIEW_FAILED);

            return $run;
        }
    }

    protected function writeTemporaryDefinition(BuilderDefinition $definition): string
    {
        $directory = storage_path('app/builder-definitions/'.$definition->getKey());
        File::ensureDirectoryExists($directory);

        $path = $directory.'/definition.json';
        File::put($path, json_encode($definition->definition_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

        return Str::after($path, base_path().DIRECTORY_SEPARATOR);
    }

    protected function manifestFromOutput(BuilderDefinition $definition, string $output): array
    {
        $moduleName = $definition->module_name ?: (string) data_get($definition->definition_json, 'module.name');
        $previewPath = storage_path('app/module-builder-preview/'.$moduleName);
        $files = [];

        if (is_dir($previewPath)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($previewPath, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $item) {
                if ($item->isFile()) {
                    $files[] = Str::after($item->getPathname(), $previewPath.DIRECTORY_SEPARATOR);
                }
            }
        }

        sort($files);

        return [
            'preview_path' => $previewPath,
            'preview_path_allowed' => str_starts_with($previewPath, storage_path('app/module-builder-preview')),
            'files' => $files,
            'real_runtime_writes_performed' => str_contains($output, 'Real runtime writes performed: 0') ? 0 : null,
            'preview_writes_reported' => $this->previewWritesFromOutput($output),
        ];
    }

    protected function previewWritesFromOutput(string $output): ?int
    {
        if (preg_match('/Preview writes performed:\s*(\d+)/', $output, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }
}
