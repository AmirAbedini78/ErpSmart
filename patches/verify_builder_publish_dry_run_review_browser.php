<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [];
$createdDefinition = null;
$dryRunRoot = null;
$readinessRoot = null;

function review_check(array &$checks, string $label, bool $passed, string $detail = ''): void
{
    $checks[] = [$label, $passed, $detail];
    echo ($passed ? 'true ' : 'false ').$label.($detail !== '' ? ' - '.$detail : '').PHP_EOL;
}

function review_read(string $root, string $path): string
{
    return file_get_contents($root.'/'.$path) ?: '';
}

function review_json(string $root, string $path): ?array
{
    $decoded = json_decode(review_read($root, $path), true);

    return is_array($decoded) ? $decoded : null;
}

function review_flatten(mixed $value): string
{
    if (is_array($value)) {
        $parts = [];
        foreach ($value as $key => $nested) {
            $parts[] = (string) $key;
            $parts[] = review_flatten($nested);
        }

        return implode(' ', $parts);
    }

    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }

    return (string) $value;
}

function review_keys(array $items): array
{
    return array_values(array_filter(array_map(static fn (array $item): string => (string) ($item['key'] ?? ''), $items)));
}

function review_delete_directory_if_unique(?string $path, string $expectedBase): void
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
use App\Services\Builder\BuilderPublishDryRunGenerator;
use Illuminate\Support\Facades\Schema;

$generatorPath = 'app/Services/Builder/BuilderPublishDryRunGenerator.php';
$uiPath = 'modules/Builder/resources/js/components/BuilderValidationPreviewPanel.vue';
$reviewComponentPath = 'modules/Builder/resources/js/components/BuilderPublishDryRunReview.vue';
$docPath = 'docs/ai/03-architecture/builder-publish-dry-run-review-browser.md';
$reviewContractPath = 'docs/ai/05-rag/contracts/builder-publish-dry-run-review-contract.json';
$dryRunContractPath = 'docs/ai/05-rag/contracts/builder-publish-dry-run-contract.json';
$historyPath = 'docs/ai/04-docops/history/2026-07-02-builder-publish-dry-run-review-browser.md';

foreach ([$generatorPath, $reviewComponentPath, $docPath, $reviewContractPath, $historyPath] as $file) {
    review_check($checks, $file.' exists', is_file($root.'/'.$file));
}

foreach ([
    $reviewContractPath,
    $dryRunContractPath,
    'docs/ai/05-rag/contracts/builder-publish-safety-contract.json',
    'docs/ai/05-rag/contracts/builder-studio-ai-rag-manifest.json',
    'docs/ai/05-rag/contracts/builder-agent-safety-boundaries.json',
    'docs/ai/05-rag/contracts/builder-studio-component-map.json',
] as $jsonFile) {
    review_check($checks, $jsonFile.' valid JSON', review_json($root, $jsonFile) !== null, json_last_error_msg());
}

$generator = review_read($root, $generatorPath);
$ui = review_read($root, $uiPath).review_read($root, $reviewComponentPath);
$manifestText = review_flatten(review_json($root, 'docs/ai/05-rag/contracts/builder-studio-ai-rag-manifest.json') ?? []);
$safetyText = review_flatten(review_json($root, 'docs/ai/05-rag/contracts/builder-agent-safety-boundaries.json') ?? []);
$componentMapText = review_flatten(review_json($root, 'docs/ai/05-rag/contracts/builder-studio-component-map.json') ?? []);

review_check($checks, 'dry-run generator includes review section', str_contains($generator, "'review' =>"));
review_check($checks, 'dry-run generator includes approval_checklist', str_contains($generator, 'approval_checklist'));
review_check($checks, 'dry-run generator includes safety_checklist', str_contains($generator, 'safety_checklist'));
review_check($checks, 'dry-run generator includes artifact_summary', str_contains($generator, 'artifact_summary'));
review_check($checks, 'review component exists', is_file($root.'/'.$reviewComponentPath));
review_check($checks, 'UI contains Review only safety notice', str_contains($ui, 'Review only. Dry-run artifacts must not be copied into runtime paths. Publish is not available in this MVP.'));
review_check($checks, 'UI says dry-run artifacts must not be copied into runtime paths', str_contains($ui, 'must not be copied into runtime paths'));
review_check($checks, 'UI contains approval checklist', str_contains($ui, 'Approval Checklist'));
review_check($checks, 'UI contains safety checklist', str_contains($ui, 'Safety Checklist'));
review_check($checks, 'UI contains forbidden actions', str_contains($ui, 'Forbidden Actions'));
review_check($checks, 'UI contains next allowed actions', str_contains($ui, 'Next Allowed Actions'));
review_check($checks, 'UI contains no Publish button/action', ! preg_match('/text=["\']Publish["\']|runPublish|publishDefinition|@publish(?!-readiness)/i', $ui));
review_check($checks, 'UI contains no Approve Publish button/action', ! preg_match('/Approve Publish|approvePublish/i', $ui));
review_check($checks, 'UI contains no Copy to runtime button/action', ! preg_match('/Copy to runtime|copyToRuntime/i', $ui));
review_check($checks, 'RAG manifest mentions dry-run review', str_contains($manifestText, 'dry-run review') || str_contains($manifestText, 'BuilderPublishDryRunReview'));
review_check($checks, 'safety boundaries forbid approve publish', str_contains($safetyText, 'approve publish'));
review_check($checks, 'safety boundaries forbid copying artifacts into runtime paths', str_contains($safetyText, 'copy publish dry-run artifacts into runtime paths'));
review_check($checks, 'component map includes BuilderPublishDryRunReview', str_contains($componentMapText, 'BuilderPublishDryRunReview'));

try {
    foreach (['builder_definitions', 'builder_definition_versions', 'builder_preview_runs'] as $table) {
        review_check($checks, $table.' table exists', Schema::hasTable($table), 'run Builder migrations if this fails');
    }

    if (array_filter($checks, static fn (array $check): bool => $check[1] === false && str_contains($check[0], ' table exists'))) {
        throw new RuntimeException('Builder tables are missing; dry-run review smoke cannot continue.');
    }

    $definition = json_decode(review_read($root, 'docs/ai/05-rag/examples/definition-driven-custom-module.json'), true);
    if (! is_array($definition)) {
        throw new RuntimeException('Unable to read definition-driven-custom-module.json');
    }

    $suffix = bin2hex(random_bytes(4));
    $moduleName = 'DryRunReviewSmoke'.$suffix;
    $route = 'dry-run-review-smoke-'.$suffix;
    $definition['module']['name'] = $moduleName;
    $definition['module']['namespace'] = 'Modules\\'.$moduleName;
    $definition['module']['singularLabel'] = 'DryRunReviewSmoke';
    $definition['module']['pluralLabel'] = $moduleName;
    $definition['module']['table'] = 'dry_run_review_smoke_'.$suffix;
    $definition['module']['routeName'] = $route;
    $definition['module']['resourceName'] = $route;
    $definition['resource']['modelClass'] = 'Modules\\'.$moduleName.'\\Models\\DryRunReviewSmoke';
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
        'name' => 'Dry Run Review Smoke '.$suffix,
        'slug' => 'dry-run-review-smoke-'.$suffix,
        'module_name' => $moduleName,
        'entity_name' => 'DryRunReviewSmoke',
        'resource_name' => $route,
        'status' => BuilderDefinition::STATUS_DRAFT,
        'schema_version' => 1,
        'definition_json' => $definition,
        'checksum' => hash('sha256', json_encode($definition, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
    ]);

    $versionCountBefore = BuilderDefinitionVersion::where('builder_definition_id', $createdDefinition->getKey())->count();
    $previewCountBefore = BuilderPreviewRun::where('builder_definition_id', $createdDefinition->getKey())->count();
    $report = $app->make(BuilderPublishDryRunGenerator::class)->generate($createdDefinition->fresh());
    $dryRunRoot = $root.'/'.$report['dry_run_root'];
    $readinessRoot = $root.'/storage/app/builder-publish-readiness/'.$createdDefinition->getKey();

    $approvalKeys = review_keys($report['review']['approval_checklist'] ?? []);
    $safetyKeys = review_keys($report['review']['safety_checklist'] ?? []);
    $requiredApprovalKeys = [
        'validation_passed',
        'no_runtime_writes',
        'no_migrations_run',
        'no_runtime_routes_registered',
        'readiness_analyzer_completed',
        'dry_run_manifest_valid',
        'blockers_empty',
        'unsupported_capabilities_reviewed',
        'form_layout_metadata_reviewed',
        'automation_metadata_reviewed',
        'rollback_requirements_reviewed',
        'human_approval_required_before_future_publish',
    ];

    review_check($checks, 'report has review section', isset($report['review']) && is_array($report['review']));
    review_check($checks, 'review requires human approval', ($report['review']['requires_human_approval'] ?? null) === true);
    review_check($checks, 'approval status is not_requested', ($report['review']['approval_status'] ?? null) === 'not_requested');
    review_check($checks, 'approval checklist contains required keys', array_diff($requiredApprovalKeys, $approvalKeys) === []);
    review_check($checks, 'safety checklist contains runtime_writes_zero', in_array('runtime_writes_zero', $safetyKeys, true));
    review_check($checks, 'safety checklist contains no_migrations_run', in_array('no_migrations_run', $safetyKeys, true));
    review_check($checks, 'safety checklist contains no_runtime_routes_registered', in_array('no_runtime_routes_registered', $safetyKeys, true));
    review_check($checks, 'artifact_summary total_files greater than 0', ($report['artifact_summary']['total_files'] ?? 0) > 0);
    review_check($checks, 'forbidden actions include publish', in_array('publish', $report['review']['forbidden_actions'] ?? [], true));
    review_check($checks, 'forbidden actions include copy artifacts into runtime paths', in_array('copy artifacts into runtime paths', $report['review']['forbidden_actions'] ?? [], true));
    review_check($checks, 'next allowed actions do not include publish', ! in_array('publish', $report['review']['next_allowed_actions'] ?? [], true));
    review_check($checks, 'report writes_performed is 0', ($report['writes_performed'] ?? null) === 0);
    review_check($checks, 'report runtime_writes_performed is 0', ($report['runtime_writes_performed'] ?? null) === 0);
    review_check($checks, 'report publish_executed is false', ($report['publish_executed'] ?? null) === false);

    $generatedContents = '';
    foreach (($report['files'] ?? []) as $file) {
        if (($file['type'] ?? '') !== 'manifest' && is_file($root.'/'.$file['dry_run_path'])) {
            $generatedContents .= file_get_contents($root.'/'.$file['dry_run_path']);
        }
    }
    review_check($checks, 'generated files contain DRY RUN ONLY', str_contains($generatedContents, 'DRY RUN ONLY - NOT RUNTIME CODE'));
    review_check($checks, 'dry-run does not create preview runs', BuilderPreviewRun::where('builder_definition_id', $createdDefinition->getKey())->count() === $previewCountBefore);
    review_check($checks, 'dry-run does not create versions', BuilderDefinitionVersion::where('builder_definition_id', $createdDefinition->getKey())->count() === $versionCountBefore);
    review_check($checks, 'no real runtime smoke module created', ! is_dir($root.'/modules/'.$moduleName));
} catch (Throwable $e) {
    review_check($checks, 'runtime dry-run review smoke completed without exception', false, $e->getMessage());
} finally {
    if ($createdDefinition instanceof BuilderDefinition) {
        $definitionId = $createdDefinition->getKey();
        BuilderPreviewRun::where('builder_definition_id', $definitionId)->delete();
        BuilderDefinitionVersion::where('builder_definition_id', $definitionId)->delete();
        BuilderDefinition::whereKey($definitionId)->delete();
        review_check($checks, 'temporary DB records cleaned', BuilderDefinition::whereKey($definitionId)->doesntExist());
    }

    review_delete_directory_if_unique($dryRunRoot, $root.'/storage/app/builder-publish-dry-runs');
    review_delete_directory_if_unique($readinessRoot, $root.'/storage/app/builder-publish-readiness');
    review_check($checks, 'unique dry-run directory cleaned or absent', ! $dryRunRoot || ! is_dir($dryRunRoot));
    review_check($checks, 'unique readiness directory cleaned or absent', ! $readinessRoot || ! is_dir($readinessRoot));
}

$statusOutput = shell_exec('cd '.escapeshellarg($root).' && git -c safe.directory='.escapeshellarg($root).' --no-pager status --short') ?: '';
review_check($checks, 'git status command succeeds', $statusOutput !== '', trim($statusOutput));
$changedPaths = array_filter(array_map(static fn (string $line): string => trim(substr($line, 3)), preg_split('/\R/', trim($statusOutput))));

foreach ([
    'app/Console/Commands/ErpsmartMakeModuleCommand.php',
    'app/Http/Controllers/Builder/',
    'routes/',
    'app/Services/Builder/BuilderPreviewService.php',
    'app/Services/Builder/BuilderDefinitionValidator.php',
    'app/Models/',
    'database/migrations/',
    'modules/Warehouse/',
    'modules/Core/',
    'modules/SaaS/',
    'modules/Updater/',
    'modules/Installer/',
    'resources/js/app.js',
    'vendor/',
    'node_modules/',
    'public/build/',
] as $forbiddenPath) {
    $changed = str_ends_with($forbiddenPath, '/')
        ? array_filter($changedPaths, static fn (string $path): bool => str_starts_with($path, $forbiddenPath))
        : in_array($forbiddenPath, $changedPaths, true);
    review_check($checks, 'no '.$forbiddenPath.' changed', ! $changed);
}

foreach (['package.json', 'package-lock.json', 'composer.json', 'composer.lock'] as $file) {
    review_check($checks, 'no '.$file.' changed', ! in_array($file, $changedPaths, true));
}

$failed = array_filter($checks, static fn (array $check): bool => $check[1] === false);
echo $failed === [] ? "PASS\n" : "FAIL\n";
exit($failed === [] ? 0 : 1);
