<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [];
$createdDefinition = null;
$candidateRoot = null;
$dryRunRoot = null;
$readinessRoot = null;

function candidate_check(array &$checks, string $label, bool $passed, string $detail = ''): void
{
    $checks[] = [$label, $passed, $detail];
    echo ($passed ? 'true ' : 'false ').$label.($detail !== '' ? ' - '.$detail : '').PHP_EOL;
}

function candidate_read(string $root, string $path): string
{
    return file_get_contents($root.'/'.$path) ?: '';
}

function candidate_json(string $root, string $path): ?array
{
    $decoded = json_decode(candidate_read($root, $path), true);

    return is_array($decoded) ? $decoded : null;
}

function candidate_flatten(mixed $value): string
{
    if (is_array($value)) {
        $parts = [];
        foreach ($value as $key => $nested) {
            $parts[] = (string) $key;
            $parts[] = candidate_flatten($nested);
        }

        return implode(' ', $parts);
    }

    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }

    return (string) $value;
}

function candidate_keys(array $items): array
{
    return array_values(array_filter(array_map(static fn (array $item): string => (string) ($item['key'] ?? ''), $items)));
}

function candidate_delete_directory_if_unique(?string $path, string $expectedBase): void
{
    if (! $path || ! is_dir($path)) {
        return;
    }

    $realPath = realpath($path);
    $realBase = realpath($expectedBase);
    if (! $realPath || ! $realBase || ! str_starts_with($realPath, $realBase.DIRECTORY_SEPARATOR)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($realPath, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($realPath);
}

require_once $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\BuilderDefinition;
use App\Models\BuilderDefinitionVersion;
use App\Models\BuilderPreviewRun;
use App\Services\Builder\BuilderPublishCandidateSnapshotService;
use Illuminate\Support\Facades\Schema;

$servicePath = 'app/Services/Builder/BuilderPublishCandidateSnapshotService.php';
$controllerPath = 'app/Http/Controllers/Builder/BuilderDefinitionController.php';
$routesPath = 'routes/api.php';
$apiPath = 'modules/Builder/resources/js/services/builderApi.js';
$uiPaths = [
    'modules/Builder/resources/js/components/BuilderValidationPreviewPanel.vue',
    'modules/Builder/resources/js/components/BuilderPublishCandidateSnapshot.vue',
    'modules/Builder/resources/js/views/BuilderDefinitionView.vue',
];
$docPath = 'docs/ai/03-architecture/builder-publish-candidate-snapshot.md';
$contractPath = 'docs/ai/05-rag/contracts/builder-publish-candidate-snapshot-contract.json';
$historyPath = 'docs/ai/04-docops/history/2026-07-02-builder-publish-candidate-snapshot.md';

foreach ([$servicePath, $docPath, $contractPath, $historyPath] as $file) {
    candidate_check($checks, $file.' exists', is_file($root.'/'.$file));
}

foreach ([
    $contractPath,
    'docs/ai/05-rag/contracts/builder-publish-safety-contract.json',
    'docs/ai/05-rag/contracts/builder-studio-api-map.json',
    'docs/ai/05-rag/contracts/builder-studio-ai-rag-manifest.json',
    'docs/ai/05-rag/contracts/builder-agent-safety-boundaries.json',
    'docs/ai/05-rag/contracts/builder-studio-component-map.json',
] as $jsonFile) {
    candidate_check($checks, $jsonFile.' valid JSON', candidate_json($root, $jsonFile) !== null, json_last_error_msg());
}

$service = candidate_read($root, $servicePath);
$controller = candidate_read($root, $controllerPath);
$routes = candidate_read($root, $routesPath);
$api = candidate_read($root, $apiPath);
$ui = implode('', array_map(fn (string $path): string => candidate_read($root, $path), $uiPaths));
$manifestText = candidate_flatten(candidate_json($root, 'docs/ai/05-rag/contracts/builder-studio-ai-rag-manifest.json') ?? []);
$apiMapText = candidate_flatten(candidate_json($root, 'docs/ai/05-rag/contracts/builder-studio-api-map.json') ?? []);
$safetyText = candidate_flatten(candidate_json($root, 'docs/ai/05-rag/contracts/builder-agent-safety-boundaries.json') ?? []);

candidate_check($checks, 'candidate snapshot service references storage/app/builder-publish-candidates', str_contains($service, 'storage/app/builder-publish-candidates'));
candidate_check($checks, 'service does not write modules/', ! preg_match('/File::put\([^;]*modules\//', $service));
candidate_check($checks, 'service does not write database/migrations/', ! preg_match('/File::put\([^;]*database\/migrations/', $service));
candidate_check($checks, 'service does not run migrations', ! preg_match('/migrate|Artisan::call|Schema::create|Schema::table/i', $service));
candidate_check($checks, 'service does not create versions or preview runs', ! preg_match('/BuilderDefinitionVersion|BuilderPreviewRun|createVersion|previewRuns\(\)->create|versions\(\)->create/', $service));
candidate_check($checks, 'controller has publishCandidateSnapshot method', preg_match('/function\s+publishCandidateSnapshot\s*\(/', $controller) === 1);
candidate_check($checks, 'route registration includes publish-candidate-snapshot', str_contains($routes, 'publish-candidate-snapshot'));
candidate_check($checks, 'builderApi has createPublishCandidateSnapshot', str_contains($api, 'function createPublishCandidateSnapshot'));
candidate_check($checks, 'UI contains Create Publish Candidate Snapshot', str_contains($ui, 'Create Publish Candidate Snapshot'));
candidate_check($checks, 'UI contains snapshot-only safety notice', str_contains($ui, 'Snapshot only. This freezes a review artifact under storage/app/builder-publish-candidates. It does not approve or execute publish.'));
candidate_check($checks, 'UI contains no Publish button/action', ! preg_match('/text=["\']Publish["\']|runPublish|publishDefinition|@publish(?!-readiness)/i', $ui.$api));
candidate_check($checks, 'UI contains no Approve Publish button/action', ! preg_match('/Approve Publish|approvePublish/i', $ui.$api));
candidate_check($checks, 'UI contains no Copy to runtime button/action', ! preg_match('/Copy to runtime|copyToRuntime/i', $ui.$api));
candidate_check($checks, 'RAG manifest mentions publish candidates', str_contains($manifestText, 'builder-publish-candidates') && str_contains($manifestText, 'candidate snapshot'));
candidate_check($checks, 'API map includes publish-candidate-snapshot endpoint', str_contains($apiMapText, '/api/builder/definitions/{id}/publish-candidate-snapshot'));
candidate_check($checks, 'safety boundaries forbid approve publish', str_contains($safetyText, 'approve publish'));
candidate_check($checks, 'safety boundaries forbid copying candidate artifacts', str_contains($safetyText, 'copy publish candidate snapshots into runtime paths'));

try {
    foreach (['builder_definitions', 'builder_definition_versions', 'builder_preview_runs'] as $table) {
        candidate_check($checks, $table.' table exists', Schema::hasTable($table), 'run Builder migrations if this fails');
    }

    if (array_filter($checks, static fn (array $check): bool => $check[1] === false && str_contains($check[0], ' table exists'))) {
        throw new RuntimeException('Builder tables are missing; candidate snapshot smoke cannot continue.');
    }

    $definition = json_decode(candidate_read($root, 'docs/ai/05-rag/examples/definition-driven-custom-module.json'), true);
    if (! is_array($definition)) {
        throw new RuntimeException('Unable to read definition-driven-custom-module.json');
    }

    $suffix = bin2hex(random_bytes(4));
    $moduleName = 'CandidateSnapshotSmoke'.$suffix;
    $route = 'candidate-snapshot-smoke-'.$suffix;
    $definition['module']['name'] = $moduleName;
    $definition['module']['namespace'] = 'Modules\\'.$moduleName;
    $definition['module']['singularLabel'] = 'CandidateSnapshotSmoke';
    $definition['module']['pluralLabel'] = $moduleName;
    $definition['module']['table'] = 'candidate_snapshot_smoke_'.$suffix;
    $definition['module']['routeName'] = $route;
    $definition['module']['resourceName'] = $route;
    $definition['resource']['modelClass'] = 'Modules\\'.$moduleName.'\\Models\\CandidateSnapshotSmoke';
    $definition['relations'] = [[
        'name' => 'owner',
        'type' => 'belongsTo',
        'targetModule' => 'Users',
        'targetModel' => 'User',
        'targetResource' => 'users',
        'foreignKey' => 'user_id',
    ]];
    $definition['formLayout'] = [
        'enabled' => true,
        'sections' => [['id' => 'main', 'fields' => [['field' => 'title']]]],
    ];
    $definition['automation'] = [
        'enabled' => true,
        'workflows' => [[
            'id' => 'workflow_1',
            'trigger' => ['type' => 'record_created'],
            'actions' => [['type' => 'send_notification']],
        ]],
    ];

    $createdDefinition = BuilderDefinition::create([
        'name' => 'Candidate Snapshot Smoke '.$suffix,
        'slug' => 'candidate-snapshot-smoke-'.$suffix,
        'module_name' => $moduleName,
        'entity_name' => 'CandidateSnapshotSmoke',
        'resource_name' => $route,
        'status' => BuilderDefinition::STATUS_DRAFT,
        'schema_version' => 1,
        'definition_json' => $definition,
        'checksum' => hash('sha256', json_encode($definition, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
    ]);

    $versionCountBefore = BuilderDefinitionVersion::where('builder_definition_id', $createdDefinition->getKey())->count();
    $previewCountBefore = BuilderPreviewRun::where('builder_definition_id', $createdDefinition->getKey())->count();
    $report = $app->make(BuilderPublishCandidateSnapshotService::class)->create($createdDefinition->fresh());
    $candidateRoot = $root.'/'.$report['candidate_root'];
    $dryRunRoot = isset($report['dry_run']['dry_run_root']) ? $root.'/'.$report['dry_run']['dry_run_root'] : null;
    $readinessRoot = $root.'/storage/app/builder-publish-readiness/'.$createdDefinition->getKey();
    $snapshotPath = $root.'/'.$report['candidate_snapshot_path'];
    $snapshot = is_file($snapshotPath) ? json_decode(file_get_contents($snapshotPath) ?: '', true) : null;
    $candidateKeys = candidate_keys($report['candidate_checklist'] ?? []);

    candidate_check($checks, 'report candidate_status snapshot_created', ($report['candidate_status'] ?? null) === 'snapshot_created');
    candidate_check($checks, 'candidate_id exists', filled($report['candidate_id'] ?? null));
    candidate_check($checks, 'candidate_root under storage/app/builder-publish-candidates', str_starts_with((string) ($report['candidate_root'] ?? ''), 'storage/app/builder-publish-candidates/'));
    candidate_check($checks, 'candidate_snapshot_path exists', is_file($snapshotPath));
    candidate_check($checks, 'candidate snapshot JSON is valid', is_array($snapshot), json_last_error_msg());
    candidate_check($checks, 'writes_performed is 0', ($report['writes_performed'] ?? null) === 0);
    candidate_check($checks, 'runtime_writes_performed is 0', ($report['runtime_writes_performed'] ?? null) === 0);
    candidate_check($checks, 'publish_executed is false', ($report['publish_executed'] ?? null) === false);
    candidate_check($checks, 'approval_requested is false', ($report['approval_requested'] ?? null) === false);
    candidate_check($checks, 'approval_granted is false', ($report['approval_granted'] ?? null) === false);
    candidate_check($checks, 'runtime_module_effect none', ($report['runtime_module_effect'] ?? null) === 'none');
    candidate_check($checks, 'candidate checklist includes readiness_report_available', in_array('readiness_report_available', $candidateKeys, true));
    candidate_check($checks, 'forbidden actions include publish', in_array('publish', $report['forbidden_actions'] ?? [], true));
    candidate_check($checks, 'forbidden actions include approve publish', in_array('approve publish', $report['forbidden_actions'] ?? [], true));
    candidate_check($checks, 'forbidden actions include copy artifacts into runtime paths', in_array('copy artifacts into runtime paths', $report['forbidden_actions'] ?? [], true));
    candidate_check($checks, 'next allowed actions do not include publish', ! in_array('publish', $report['next_allowed_actions'] ?? [], true));
    candidate_check($checks, 'candidate service does not create preview runs', BuilderPreviewRun::where('builder_definition_id', $createdDefinition->getKey())->count() === $previewCountBefore);
    candidate_check($checks, 'candidate service does not create versions', BuilderDefinitionVersion::where('builder_definition_id', $createdDefinition->getKey())->count() === $versionCountBefore);
    candidate_check($checks, 'no real runtime smoke module created', ! is_dir($root.'/modules/'.$moduleName));
} catch (Throwable $e) {
    candidate_check($checks, 'runtime candidate snapshot smoke completed without exception', false, $e->getMessage());
} finally {
    if ($createdDefinition instanceof BuilderDefinition) {
        $definitionId = $createdDefinition->getKey();
        BuilderPreviewRun::where('builder_definition_id', $definitionId)->delete();
        BuilderDefinitionVersion::where('builder_definition_id', $definitionId)->delete();
        BuilderDefinition::whereKey($definitionId)->delete();
        candidate_check($checks, 'temporary DB records cleaned', BuilderDefinition::whereKey($definitionId)->doesntExist());
    }

    candidate_delete_directory_if_unique($candidateRoot, $root.'/storage/app/builder-publish-candidates');
    candidate_delete_directory_if_unique($dryRunRoot, $root.'/storage/app/builder-publish-dry-runs');
    candidate_delete_directory_if_unique($readinessRoot, $root.'/storage/app/builder-publish-readiness');
    candidate_check($checks, 'unique candidate directory cleaned or absent', ! $candidateRoot || ! is_dir($candidateRoot));
    candidate_check($checks, 'unique dry-run directory cleaned or absent', ! $dryRunRoot || ! is_dir($dryRunRoot));
    candidate_check($checks, 'unique readiness directory cleaned or absent', ! $readinessRoot || ! is_dir($readinessRoot));
}

$statusOutput = shell_exec('cd '.escapeshellarg($root).' && git -c safe.directory='.escapeshellarg($root).' --no-pager status --short') ?: '';
candidate_check($checks, 'git status command succeeds', $statusOutput !== '', trim($statusOutput));
$changedPaths = array_filter(array_map(static fn (string $line): string => trim(substr($line, 3)), preg_split('/\R/', trim($statusOutput))));

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
    'routes/web.php',
    'resources/js/app.js',
    'vendor/',
    'node_modules/',
    'public/build/',
] as $forbiddenPath) {
    $changed = str_ends_with($forbiddenPath, '/')
        ? array_filter($changedPaths, static fn (string $path): bool => str_starts_with($path, $forbiddenPath))
        : in_array($forbiddenPath, $changedPaths, true);
    candidate_check($checks, 'no '.$forbiddenPath.' changed', ! $changed);
}

foreach (['package.json', 'package-lock.json', 'composer.json', 'composer.lock'] as $file) {
    candidate_check($checks, 'no '.$file.' changed', ! in_array($file, $changedPaths, true));
}

$failed = array_filter($checks, static fn (array $check): bool => $check[1] === false);
echo $failed === [] ? "PASS\n" : "FAIL\n";
exit($failed === [] ? 0 : 1);
