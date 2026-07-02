<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [];
$createdDefinition = null;

function readiness_check(array &$checks, string $label, bool $passed, string $detail = ''): void
{
    $checks[] = [$label, $passed, $detail];
    echo ($passed ? 'true ' : 'false ').$label.($detail !== '' ? ' - '.$detail : '').PHP_EOL;
}

function readiness_read(string $root, string $path): string
{
    return file_get_contents($root.'/'.$path) ?: '';
}

function readiness_json(string $root, string $path): ?array
{
    $decoded = json_decode(readiness_read($root, $path), true);

    return is_array($decoded) ? $decoded : null;
}

function readiness_flatten(mixed $value): string
{
    if (is_array($value)) {
        $parts = [];

        foreach ($value as $key => $nested) {
            $parts[] = (string) $key;
            $parts[] = readiness_flatten($nested);
        }

        return implode(' ', $parts);
    }

    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }

    return (string) $value;
}

require_once $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\BuilderDefinition;
use App\Models\BuilderDefinitionVersion;
use App\Models\BuilderPreviewRun;
use App\Services\Builder\BuilderPublishReadinessAnalyzer;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

$analyzerPath = 'app/Services/Builder/BuilderPublishReadinessAnalyzer.php';
$controllerPath = 'app/Http/Controllers/Builder/BuilderDefinitionController.php';
$routesPath = 'routes/api.php';
$apiPath = 'modules/Builder/resources/js/services/builderApi.js';
$viewPath = 'modules/Builder/resources/js/views/BuilderDefinitionView.vue';
$panelPath = 'modules/Builder/resources/js/components/BuilderValidationPreviewPanel.vue';
$docPath = 'docs/ai/03-architecture/builder-publish-readiness-analyzer.md';
$contractPath = 'docs/ai/05-rag/contracts/builder-publish-readiness-report-contract.json';
$historyPath = 'docs/ai/04-docops/history/2026-07-02-builder-publish-readiness-analyzer.md';

foreach ([$analyzerPath, $docPath, $contractPath, $historyPath] as $file) {
    readiness_check($checks, $file.' exists', is_file($root.'/'.$file));
}

foreach ([
    $contractPath,
    'docs/ai/05-rag/contracts/builder-studio-api-map.json',
    'docs/ai/05-rag/contracts/builder-publish-safety-contract.json',
    'docs/ai/05-rag/contracts/builder-module-dependency-impact-map.json',
    'docs/ai/05-rag/contracts/builder-studio-ai-rag-manifest.json',
    'docs/ai/05-rag/contracts/builder-agent-safety-boundaries.json',
] as $jsonFile) {
    readiness_check($checks, $jsonFile.' valid JSON', readiness_json($root, $jsonFile) !== null, json_last_error_msg());
}

$analyzerSource = readiness_read($root, $analyzerPath);
$controllerSource = readiness_read($root, $controllerPath);
$routesSource = readiness_read($root, $routesPath);
$apiSource = readiness_read($root, $apiPath);
$uiSource = readiness_read($root, $viewPath).readiness_read($root, $panelPath);
$manifestText = readiness_flatten(readiness_json($root, 'docs/ai/05-rag/contracts/builder-studio-ai-rag-manifest.json') ?? []);
$apiMapText = readiness_flatten(readiness_json($root, 'docs/ai/05-rag/contracts/builder-studio-api-map.json') ?? []);
$safetyText = readiness_flatten(readiness_json($root, 'docs/ai/05-rag/contracts/builder-agent-safety-boundaries.json') ?? []);

readiness_check($checks, 'analyzer service defines analyze method', str_contains($analyzerSource, 'function analyze('));
readiness_check($checks, 'analyzer calls validator', str_contains($analyzerSource, 'BuilderDefinitionValidator') && str_contains($analyzerSource, '->validate('));
readiness_check($checks, 'analyzer reports writes_performed 0', str_contains($analyzerSource, "'writes_performed' => 0"));
readiness_check($checks, 'analyzer reports publish_executed false', str_contains($analyzerSource, "'publish_executed' => false"));
readiness_check($checks, 'analyzer does not call preview service', ! str_contains($analyzerSource, 'BuilderPreviewService'));
readiness_check($checks, 'controller has publishReadiness method', preg_match('/function\s+publishReadiness\s*\(/', $controllerSource) === 1);
readiness_check($checks, 'route registration includes publish-readiness', str_contains($routesSource, 'publish-readiness'));
readiness_check($checks, 'builderApi has analyzePublishReadiness', str_contains($apiSource, 'function analyzePublishReadiness'));
readiness_check($checks, 'UI contains Analyze Publish Readiness', str_contains($uiSource, 'Analyze Publish Readiness'));
readiness_check($checks, 'UI has analysis-only safety notice', str_contains($uiSource, 'Analysis only. No runtime files, modules, migrations, tables, or publish actions are performed.'));
readiness_check($checks, 'UI contains no exact publish button/action', ! preg_match('/text=["\']Publish["\']|runPublish|publishDefinition|@publish(?!-readiness)/i', $uiSource.$apiSource));
readiness_check($checks, 'RAG manifest mentions publish readiness analyzer', str_contains($manifestText, 'BuilderPublishReadinessAnalyzer') && str_contains($manifestText, 'analyze publish readiness'));
readiness_check($checks, 'API map includes publish-readiness endpoint', str_contains($apiMapText, '/api/builder/definitions/{id}/publish-readiness'));
readiness_check($checks, 'API map marks actual_publish false', str_contains($apiMapText, 'actual_publish false'));
readiness_check($checks, 'safety boundaries allow analyzer', str_contains($safetyText, 'call publish readiness analyzer'));
readiness_check($checks, 'safety boundaries forbid publish', str_contains($safetyText, 'execute publish') && str_contains($safetyText, 'treat publish readiness as actual publish'));

try {
    foreach (['builder_definitions', 'builder_definition_versions', 'builder_preview_runs'] as $table) {
        readiness_check($checks, $table.' table exists', Schema::hasTable($table), 'run Builder migrations if this fails');
    }

    if (array_filter($checks, static fn (array $check): bool => $check[1] === false && str_contains($check[0], ' table exists'))) {
        throw new RuntimeException('Builder tables are missing; analyzer smoke cannot continue.');
    }

    $definition = json_decode(readiness_read($root, 'docs/ai/05-rag/examples/definition-driven-custom-module.json'), true);
    if (! is_array($definition)) {
        throw new RuntimeException('Unable to read definition-driven-custom-module.json');
    }

    $suffix = bin2hex(random_bytes(4));
    $moduleName = 'ReadinessSmoke'.$suffix;
    $table = 'readiness_smoke_'.$suffix;
    $route = 'readiness-smoke-'.$suffix;
    $definition['module']['name'] = $moduleName;
    $definition['module']['namespace'] = 'Modules\\'.$moduleName;
    $definition['module']['singularLabel'] = 'ReadinessSmoke';
    $definition['module']['pluralLabel'] = $moduleName;
    $definition['module']['table'] = $table;
    $definition['module']['routeName'] = $route;
    $definition['module']['resourceName'] = $route;
    $definition['resource']['modelClass'] = 'Modules\\'.$moduleName.'\\Models\\ReadinessSmoke';

    $createdDefinition = BuilderDefinition::create([
        'name' => 'Readiness Smoke '.$suffix,
        'slug' => 'readiness-smoke-'.$suffix,
        'module_name' => $moduleName,
        'entity_name' => 'ReadinessSmoke',
        'resource_name' => $route,
        'status' => BuilderDefinition::STATUS_DRAFT,
        'schema_version' => 1,
        'definition_json' => $definition,
        'checksum' => hash('sha256', json_encode($definition, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
    ]);

    $versionCountBefore = BuilderDefinitionVersion::where('builder_definition_id', $createdDefinition->getKey())->count();
    $previewCountBefore = BuilderPreviewRun::where('builder_definition_id', $createdDefinition->getKey())->count();

    $report = $app->make(BuilderPublishReadinessAnalyzer::class)->analyze($createdDefinition->fresh());

    readiness_check($checks, 'analyzer returns array report', is_array($report));
    readiness_check($checks, 'report writes_performed is 0', ($report['writes_performed'] ?? null) === 0);
    readiness_check($checks, 'report publish_executed is false', ($report['publish_executed'] ?? null) === false);
    readiness_check($checks, 'report runtime_module_effect none', ($report['runtime_module_effect'] ?? null) === 'none');

    foreach (['file_plan', 'database_plan', 'capability_impact', 'conflicts', 'blockers', 'warnings', 'rollback_requirements'] as $key) {
        readiness_check($checks, 'report has '.$key, array_key_exists($key, $report));
    }

    readiness_check($checks, 'report has dependency_checks', array_key_exists('dependency_checks', $report));
    readiness_check($checks, 'report has validation', array_key_exists('validation', $report));
    readiness_check($checks, 'analyzer does not create preview runs', BuilderPreviewRun::where('builder_definition_id', $createdDefinition->getKey())->count() === $previewCountBefore);
    readiness_check($checks, 'analyzer does not create versions', BuilderDefinitionVersion::where('builder_definition_id', $createdDefinition->getKey())->count() === $versionCountBefore);
    readiness_check($checks, 'no real runtime smoke module created', ! is_dir($root.'/modules/'.$moduleName));
    readiness_check($checks, 'report planned table', in_array($table, $report['database_plan']['would_create_tables'] ?? [], true));
    readiness_check($checks, 'report planned files without writing them', ($report['file_plan']['would_create'] ?? []) !== [] && ! is_file($root.'/modules/'.$moduleName.'/module.json'));

    Artisan::call('route:list', ['--path' => 'builder']);
    $routes = Artisan::output();
    readiness_check($checks, 'route:list contains publish-readiness', str_contains($routes, 'publish-readiness'));
} catch (Throwable $e) {
    readiness_check($checks, 'runtime analyzer smoke completed without exception', false, $e->getMessage());
} finally {
    if ($createdDefinition instanceof BuilderDefinition) {
        $definitionId = $createdDefinition->getKey();
        BuilderPreviewRun::where('builder_definition_id', $definitionId)->delete();
        BuilderDefinitionVersion::where('builder_definition_id', $definitionId)->delete();
        BuilderDefinition::whereKey($definitionId)->delete();
        readiness_check($checks, 'temporary DB records cleaned', BuilderDefinition::whereKey($definitionId)->doesntExist());
    }
}

$statusOutput = shell_exec('cd '.escapeshellarg($root).' && git -c safe.directory='.escapeshellarg($root).' --no-pager status --short') ?: '';
readiness_check($checks, 'git status command succeeds', $statusOutput !== '', trim($statusOutput));

$changedPaths = array_filter(array_map(
    static fn (string $line): string => trim(substr($line, 3)),
    preg_split('/\R/', trim($statusOutput))
));

foreach ([
    'app/Console/Commands/ErpsmartMakeModuleCommand.php',
    'app/Services/Builder/BuilderPreviewService.php',
    'app/Services/Builder/BuilderDefinitionValidator.php',
    'app/Models/',
    'database/migrations/',
    'modules/Warehouse/',
    'modules/Core/',
    'modules/SaaS/',
    'modules/Updater/',
    'modules/Installer/',
    'vendor/',
    'node_modules/',
    'public/build/',
] as $forbiddenPath) {
    $changed = str_ends_with($forbiddenPath, '/')
        ? array_filter($changedPaths, static fn (string $path): bool => str_starts_with($path, $forbiddenPath))
        : in_array($forbiddenPath, $changedPaths, true);

    readiness_check($checks, 'no '.$forbiddenPath.' changed', ! $changed);
}

foreach (['package.json', 'package-lock.json', 'composer.json', 'composer.lock'] as $file) {
    readiness_check($checks, 'no '.$file.' changed', ! in_array($file, $changedPaths, true));
}

$failed = array_filter($checks, static fn (array $check): bool => $check[1] === false);

echo $failed === [] ? "PASS\n" : "FAIL\n";

exit($failed === [] ? 0 : 1);
