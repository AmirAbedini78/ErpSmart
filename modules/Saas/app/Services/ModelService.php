<?php

namespace Modules\Saas\Services;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Modules\Core\Facades\Module as ModuleFacade;
use Modules\Core\Models\Model;
use Modules\Core\Module\Module;

class ModelService
{
    protected Filesystem $files;

    public function __construct()
    {
        $this->files = new Filesystem;
    }

    public function getQuotableModels(): Collection
    {
        $models = collect();
        $activeModules = ModuleFacade::allEnabled();
        foreach ($activeModules as $activeModule) {
            if ($activeModule->getLowerName() == 'core' || $activeModule->getLowerName() == 'saas') {
                continue;
            }
            $models = $models->merge($this->getModels($activeModule));
        }

        return $models;
    }

    public function getAllModels(): Collection
    {
        $models = collect();
        $activeModules = ModuleFacade::allEnabled();
        foreach ($activeModules as $activeModule) {
            $models = $models->merge($this->getModels($activeModule));
        }

        return $models;
    }

    protected function getModels(Module $module): array
    {
        $models = $this->filesToNamespace(
            $this->retreiveFiles($module->getAppPath().DIRECTORY_SEPARATOR.'Models')
        );

        return array_filter($models, fn ($model) => class_exists($model) && is_subclass_of($model, Model::class));
    }

    protected function retreiveFiles(string $dir): array
    {
        if (! $this->files->isDirectory($dir)) {
            return [];
        }

        return $this->files->allFiles($dir);
    }

    protected function filesToNamespace(array $files, $rootNamespace = 'Modules', $excludeDir = 'app'): array
    {
        $baseDir = base_path('modules');

        $namespaces = [];

        foreach ($files as $file) {

            $filePath = $file->getRealPath();

            $filePath = str_replace('/', DIRECTORY_SEPARATOR, $filePath);
            $baseDir = str_replace('/', DIRECTORY_SEPARATOR, $baseDir);

            if (substr($baseDir, -1) != DIRECTORY_SEPARATOR) {
                $baseDir .= DIRECTORY_SEPARATOR;
            }

            if (substr($filePath, 0, strlen($baseDir)) == $baseDir) {
                $relativeClassPath = substr($filePath, strlen($baseDir));
            } else {
                return [];
            }

            if (! empty($excludeDir)) {
                $relativeClassPath = str_replace($excludeDir.DIRECTORY_SEPARATOR, '', $relativeClassPath);
            }

            $className = rtrim($relativeClassPath, '.php');

            $namespace = str_replace(DIRECTORY_SEPARATOR, '\\', $className);

            if (! empty($rootNamespace)) {
                $namespace = rtrim($rootNamespace, '\\').'\\'.ltrim($namespace, '\\');
            }

            // Remove any leading backslash if root namespace is not provided
            if (empty($rootNamespace)) {
                $namespace = ltrim($namespace, '\\');
            }
            $namespaces[] = $namespace;
        }

        return $namespaces;
    }
}
