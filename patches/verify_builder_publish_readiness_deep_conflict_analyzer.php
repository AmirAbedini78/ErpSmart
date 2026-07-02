<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [];
$createdDefinition = null;
$artifactDirectory = null;

function deep_check(array &$checks, string $label, bool $passed, string $detail = ''): void
{
    $checks[] = [$label, $passed, $detail];
    echo ($passed ? 'true ' : 'false ').$label.($detail !== '' ? ' - '.$detail : '').PHP_EOL;
}

function deep_read(string $root, string $path): string
{
    return file_get_contents($root.'/'.$path) ?: '';
}

function deep_json(string $root, string $path): ?array
{
    $decoded = json_decode(deep_read($root, $path), true);

    return is_array($decoded) ? $decoded : null;
}

function deep_flatten(mixed $value): string
{
    if (is_array($value)) {
        $parts = [];

        foreach ($value as $key => $nested) {
            $parts[] = (string) $key;
            $parts[] = deep_flatten($nested);
        }

        return implode(' ', $parts);
    }

    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }

    return (string) $value;
}

function deep_delete_directory_if_unique(?string $path, string $expectedBase): void
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
use App\Services\Builder\BuilderPublishReadinessAnalyzer;
use Illuminate\Support\Facades\Schema;

$analyzerPath = 'app/Services/Builder/BuilderPublishReadinessAnalyzer.php';
$uiPath = 'modules/Builder/resources/js/components/BuilderValidationPreviewPanel.vue';
$docPath = 'docs/ai/03-architecture/builder-publish-readiness-deep-conflict-analyzer.md';
$artifactContractPath = 'docs/ai/05-rag/contracts/builder-publish-readiness-plan-artifact-contract.json';
$reportContractPath = 'docs/ai/05-rag/contracts/builder-publish-readiness-report-contract.json';
$historyPath = 'docs/ai/04-docops/history/2026-07-02-builder-publish-readiness-deep-conflict-analyzer.md';

foreach ([$docPath, $artifactContractPath, $reportContractPath, $historyPath] as $file) {
    deep_check($checks, $file.' exists', is_file($root.'/'.$file));
}

foreach ([
    $artifactContractPath,
    $reportContractPath,
    'docs/ai/05-rag/contracts/builder-module-dependency-impact-map.json',
    'docs/ai/05-rag/contracts/builder-publish-safety-contract.json',
    'docs/ai/05-rag/contracts/builder-studio-ai-rag-manifest.json',
    'docs/ai/05-rag/contracts/builder-agent-safety-boundaries.json',
] as $jsonFile) {
    deep_check($checks, $jsonFile.' valid JSON', deep_json($root, $jsonFile) !== null, json_last_error_msg());
}

$analyzerSource = deep_read($root, $analyzerPath);
$uiSource = deep_read($root, $uiPath);
$manifestText = deep_flatten(deep_json($root, 'docs/ai/05-rag/contracts/builder-studio-ai-rag-manifest.json') ?? []);
$safetyText = deep_flatten(deep_json($root, 'docs/ai/05-rag/contracts/builder-agent-safety-boundaries.json') ?? []);

foreach ([
    'identity_checks',
    'existing_app_conflicts',
    'field_impact',
    'relation_impact',
    'form_layout_impact',
    'automation_impact',
    'diagnostic_artifact_path',
    'diagnostic_artifacts_written',
] as $section) {
    deep_check($checks, 'analyzer has '.$section, str_contains($analyzerSource, "'".$section."'"));
}

deep_check(
    $checks,
    'analyzer writes only under storage/app/builder-publish-readiness',
    str_contains($analyzerSource, 'storage/app/builder-publish-readiness') &&
        str_contains($analyzerSource, 'File::put($absolutePath') &&
        ! preg_match('/File::put\([^;]*(modules\/|database\/migrations|public\/build)/', $analyzerSource)
);
deep_check($checks, 'analyzer does not call preview service', ! str_contains($analyzerSource, 'BuilderPreviewService'));
deep_check($checks, 'analyzer does not create versions or preview runs', ! preg_match('/BuilderDefinitionVersion|BuilderPreviewRun|createVersion|previewRuns\(\)->create|versions\(\)->create/', $analyzerSource));

foreach ([
    'Identity Checks',
    'Existing App Conflicts',
    'Field Impact',
    'Relation Impact',
    'Form Layout Impact',
    'Automation Impact',
    'Capability impact',
    'File plan',
    'Database plan',
    'Rollback requirements',
] as $label) {
    deep_check($checks, 'UI contains grouped label '.$label, str_contains($uiSource, $label));
}

deep_check($checks, 'UI safety notice remains', str_contains($uiSource, 'Analysis only. No runtime files, modules, migrations, tables, or publish actions are performed.'));
deep_check($checks, 'no Publish button/action exists', ! preg_match('/text=["\']Publish["\']|runPublish|publishDefinition|@publish(?!-readiness)/i', $uiSource));
deep_check($checks, 'RAG manifest mentions diagnostic artifact', str_contains($manifestText, 'builder-publish-readiness') && str_contains($manifestText, 'diagnostic artifact'));
deep_check($checks, 'safety boundaries forbid treating artifact as runtime module', str_contains($safetyText, 'diagnostic artifact as a runtime module'));

try {
    foreach (['builder_definitions', 'builder_definition_versions', 'builder_preview_runs'] as $table) {
        deep_check($checks, $table.' table exists', Schema::hasTable($table), 'run Builder migrations if this fails');
    }

    if (array_filter($checks, static fn (array $check): bool => $check[1] === false && str_contains($check[0], ' table exists'))) {
        throw new RuntimeException('Builder tables are missing; analyzer smoke cannot continue.');
    }

    $definition = json_decode(deep_read($root, 'docs/ai/05-rag/examples/definition-driven-custom-module.json'), true);
    if (! is_array($definition)) {
        throw new RuntimeException('Unable to read definition-driven-custom-module.json');
    }

    $suffix = bin2hex(random_bytes(4));
    $moduleName = 'DeepReadinessSmoke'.$suffix;
    $table = 'deep_readiness_smoke_'.$suffix;
    $route = 'deep-readiness-smoke-'.$suffix;
    $definition['module']['name'] = $moduleName;
    $definition['module']['namespace'] = 'Modules\\'.$moduleName;
    $definition['module']['singularLabel'] = 'DeepReadinessSmoke';
    $definition['module']['pluralLabel'] = $moduleName;
    $definition['module']['table'] = $table;
    $definition['module']['routeName'] = $route;
    $definition['module']['resourceName'] = $route;
    $definition['resource']['modelClass'] = 'Modules\\'.$moduleName.'\\Models\\DeepReadinessSmoke';
    $definition['resource']['titleField'] = 'title';
    $definition['fields'][] = [
        'name' => 'category_id',
        'type' => 'belongsTo',
        'label' => 'Category',
        'required' => false,
        'primary' => false,
        'rules' => [],
        'creationRules' => [],
        'updateRules' => [],
        'visibility' => ['index' => true, 'detail' => true, 'create' => true, 'update' => true],
    ];
    $definition['relations'] = [
        [
            'name' => 'category',
            'type' => 'belongsTo',
            'targetModule' => 'MissingTargetModule',
            'targetModel' => '',
            'targetResource' => '',
            'foreignKey' => '',
            'showOnDetail' => true,
            'showOnIndex' => false,
        ],
    ];
    $definition['formLayout'] = [
        'enabled' => true,
        'mode' => 'stepper',
        'sections' => [
            [
                'id' => 'section_main',
                'label' => 'Main',
                'columns' => 2,
                'fields' => [
                    ['field' => 'title', 'order' => 1],
                    ['field' => 'missing_layout_field', 'order' => 2],
                ],
            ],
        ],
        'stepper' => ['enabled' => true, 'steps' => [['id' => 'step_main', 'label' => 'Main', 'sectionIds' => ['section_main']]],
        ],
        'conditions' => [
            ['id' => 'condition_1', 'targetField' => 'missing_condition_field', 'operator' => 'equals', 'value' => 'yes'],
        ],
    ];
    $definition['automation'] = [
        'enabled' => true,
        'workflows' => [
            [
                'id' => 'workflow_1',
                'name' => 'Smoke Workflow',
                'trigger' => ['type' => 'field_changed', 'field' => 'missing_trigger_field'],
                'conditions' => [['id' => 'condition_1', 'field' => 'missing_automation_field', 'operator' => 'equals']],
                'actions' => [
                    ['id' => 'action_1', 'type' => 'send_email', 'enabled' => true],
                    ['id' => 'action_2', 'type' => 'create_task', 'enabled' => true],
                    ['id' => 'action_3', 'type' => 'request_approval', 'enabled' => true],
                    ['id' => 'action_4', 'type' => 'webhook', 'enabled' => true],
                ],
            ],
        ],
    ];

    $createdDefinition = BuilderDefinition::create([
        'name' => 'Deep Readiness Smoke '.$suffix,
        'slug' => 'deep-readiness-smoke-'.$suffix,
        'module_name' => $moduleName,
        'entity_name' => 'DeepReadinessSmoke',
        'resource_name' => $route,
        'status' => BuilderDefinition::STATUS_DRAFT,
        'schema_version' => 1,
        'definition_json' => $definition,
        'checksum' => hash('sha256', json_encode($definition, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
    ]);

    $versionCountBefore = BuilderDefinitionVersion::where('builder_definition_id', $createdDefinition->getKey())->count();
    $previewCountBefore = BuilderPreviewRun::where('builder_definition_id', $createdDefinition->getKey())->count();
    $report = $app->make(BuilderPublishReadinessAnalyzer::class)->analyze($createdDefinition->fresh());

    foreach ([
        'identity_checks',
        'existing_app_conflicts',
        'field_impact',
        'relation_impact',
        'form_layout_impact',
        'automation_impact',
        'capability_impact',
    ] as $section) {
        deep_check($checks, 'runtime report has '.$section, array_key_exists($section, $report));
    }

    deep_check($checks, 'writes_performed is 0', ($report['writes_performed'] ?? null) === 0);
    deep_check($checks, 'publish_executed is false', ($report['publish_executed'] ?? null) === false);
    deep_check($checks, 'runtime_module_effect none', ($report['runtime_module_effect'] ?? null) === 'none');
    deep_check($checks, 'diagnostic_artifacts_written is 1', ($report['diagnostic_artifacts_written'] ?? null) === 1);
    deep_check($checks, 'diagnostic artifact path is under storage/app/builder-publish-readiness', str_starts_with((string) ($report['diagnostic_artifact_path'] ?? ''), 'storage/app/builder-publish-readiness/'));

    $artifactPath = $root.'/'.($report['diagnostic_artifact_path'] ?? '');
    $artifactDirectory = dirname($artifactPath);
    $artifact = is_file($artifactPath) ? json_decode(file_get_contents($artifactPath) ?: '', true) : null;
    deep_check($checks, 'diagnostic artifact exists', is_file($artifactPath));
    deep_check($checks, 'diagnostic artifact JSON is valid', is_array($artifact), json_last_error_msg());
    deep_check($checks, 'diagnostic artifact includes full report', is_array($artifact) && isset($artifact['report']['identity_checks'], $artifact['report']['automation_impact']));
    deep_check($checks, 'form layout missing references detected', in_array('missing_layout_field', $report['form_layout_impact']['missing_field_references'] ?? [], true));
    deep_check($checks, 'automation runtime execution forbidden', ($report['automation_impact']['runtime_execution_forbidden'] ?? false) === true);
    deep_check($checks, 'analyzer does not create preview runs', BuilderPreviewRun::where('builder_definition_id', $createdDefinition->getKey())->count() === $previewCountBefore);
    deep_check($checks, 'analyzer does not create versions', BuilderDefinitionVersion::where('builder_definition_id', $createdDefinition->getKey())->count() === $versionCountBefore);
    deep_check($checks, 'no real runtime smoke module created', ! is_dir($root.'/modules/'.$moduleName));
} catch (Throwable $e) {
    deep_check($checks, 'runtime deep analyzer smoke completed without exception', false, $e->getMessage());
} finally {
    if ($createdDefinition instanceof BuilderDefinition) {
        $definitionId = $createdDefinition->getKey();
        BuilderPreviewRun::where('builder_definition_id', $definitionId)->delete();
        BuilderDefinitionVersion::where('builder_definition_id', $definitionId)->delete();
        BuilderDefinition::whereKey($definitionId)->delete();
        deep_check($checks, 'temporary DB records cleaned', BuilderDefinition::whereKey($definitionId)->doesntExist());
    }

    deep_delete_directory_if_unique($artifactDirectory, $root.'/storage/app/builder-publish-readiness');
    deep_check($checks, 'unique diagnostic artifact directory cleaned or absent', ! $artifactDirectory || ! is_dir($artifactDirectory));
}

$statusOutput = shell_exec('cd '.escapeshellarg($root).' && git -c safe.directory='.escapeshellarg($root).' --no-pager status --short') ?: '';
deep_check($checks, 'git status command succeeds', $statusOutput !== '', trim($statusOutput));

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

    deep_check($checks, 'no '.$forbiddenPath.' changed', ! $changed);
}

foreach (['package.json', 'package-lock.json', 'composer.json', 'composer.lock'] as $file) {
    deep_check($checks, 'no '.$file.' changed', ! in_array($file, $changedPaths, true));
}

$failed = array_filter($checks, static fn (array $check): bool => $check[1] === false);

echo $failed === [] ? "PASS\n" : "FAIL\n";

exit($failed === [] ? 0 : 1);
