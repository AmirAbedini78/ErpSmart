<?php

$root = dirname(__DIR__);

function check(string $name, bool $result): bool
{
    printf('%-76s : %s%s', $name, $result ? 'true' : 'false', PHP_EOL);

    return $result;
}

function read_json(string $path): ?array
{
    if (! is_file($path)) {
        return null;
    }

    $json = json_decode(file_get_contents($path) ?: '', true);

    return is_array($json) ? $json : null;
}

function has_path(array $data, string $path): bool
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

function git_changed_files(string $root): array
{
    $command = 'cd '.escapeshellarg($root).' && git status --porcelain --untracked-files=all 2>/dev/null';
    $output = shell_exec($command);

    if (! is_string($output) || trim($output) === '') {
        return [];
    }

    return array_values(array_filter(array_map(function (string $line): string {
        $path = trim(substr($line, 3));

        if (str_contains($path, ' -> ')) {
            $parts = explode(' -> ', $path);
            $path = end($parts);
        }

        return trim($path, "\" \t\n\r\0\x0B");
    }, explode(PHP_EOL, trim($output)))));
}

function basic_validate_definition(array $definition): array
{
    $errors = [];

    $requiredPaths = [
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
        'fields',
        'capabilities.tableable',
        'permissions.view',
        'frontend.routes',
        'verifier.generate',
    ];

    foreach ($requiredPaths as $path) {
        if (! has_path($definition, $path)) {
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

$docsPath = $root.'/docs/ai/03-architecture/module-builder-mvp-schema.md';
$schemaPath = $root.'/docs/ai/05-rag/contracts/module-builder-mvp-schema.json';
$historyPath = $root.'/docs/ai/04-docops/history/2026-06-30-module-builder-mvp-schema-and-dry-run.md';

$schema = read_json($schemaPath);

$sample = [
    'schemaVersion' => 1,
    'module' => [
        'name' => 'Inventory',
        'namespace' => 'Modules\\Inventory',
        'singularLabel' => 'Item',
        'pluralLabel' => 'Items',
        'table' => 'items',
        'routeName' => 'items',
        'resourceName' => 'items',
        'icon' => 'ArchiveBox',
    ],
    'resource' => [
        'modelClass' => 'Modules\\Inventory\\Models\\Item',
        'titleField' => 'name',
        'orderBy' => 'name',
        'hasDetailView' => true,
        'globalSearchAction' => 'float',
    ],
    'fields' => [
        [
            'name' => 'name',
            'type' => 'text',
            'label' => 'Name',
            'primary' => true,
            'required' => true,
            'rules' => ['string', 'max:191'],
            'creationRules' => ['required'],
            'updateRules' => ['filled'],
            'visibility' => ['index' => true, 'detail' => true, 'create' => true, 'update' => true, 'settings' => true],
            'table' => ['width' => '300px', 'minWidth' => '200px', 'primary' => true, 'route' => '/items/{id}'],
        ],
        [
            'name' => 'is_active',
            'type' => 'boolean',
            'label' => 'Active',
            'default' => true,
            'rules' => ['nullable', 'boolean'],
            'visibility' => ['index' => true, 'detail' => true, 'create' => true, 'update' => true, 'settings' => true],
        ],
    ],
    'table' => [
        'defaultView' => ['name' => 'Items', 'flag' => 'all-items'],
        'orderBy' => ['column' => 'created_at', 'direction' => 'desc'],
    ],
    'detailPage' => [
        'panels' => [
            ['id' => 'item-detail-panel', 'component' => 'resource-details-panel', 'heading' => 'Details', 'resizeable' => true],
            ['id' => 'media', 'component' => 'resource-media-panel', 'heading' => 'Attachments', 'whenCapability' => 'mediable'],
        ],
        'tabs' => [
            ['id' => 'activities', 'component' => 'activities-tab', 'panelComponent' => 'activities-tab-panel', 'order' => 15, 'whenCapability' => 'activities'],
            ['id' => 'notes', 'component' => 'notes-tab', 'panelComponent' => 'notes-tab-panel', 'order' => 35, 'whenCapability' => 'notes'],
        ],
    ],
    'capabilities' => [
        'tableable' => true,
        'customFields' => true,
        'uniqueCustomFields' => true,
        'importable' => true,
        'exportable' => true,
        'cloneable' => true,
        'mediable' => true,
        'notes' => true,
        'activities' => true,
        'activityComments' => true,
        'activityAssociations' => true,
        'globalSearch' => true,
        'quickCreate' => true,
        'bulkDelete' => true,
        'softDeletes' => false,
        'timeline' => false,
    ],
    'permissions' => [
        'view' => ['view all items'],
        'create' => 'create items',
        'edit' => ['edit all items'],
        'delete' => ['delete any item'],
        'bulkDelete' => 'bulk delete items',
        'import' => 'import items',
        'export' => 'export items',
    ],
    'frontend' => [
        'routes' => true,
        'views' => ['index', 'create', 'edit', 'detail'],
        'floatingModal' => true,
        'localTabComponents' => [
            'activities-tab' => 'ActivitiesTab',
            'activities-tab-panel' => 'ActivitiesTabPanel',
            'notes-tab' => 'RecordTabNote',
            'notes-tab-panel' => 'RecordTabNotePanel',
        ],
    ],
    'verifier' => [
        'generate' => true,
        'path' => 'patches/verify_inventory_item_contract.php',
        'checks' => ['resource', 'json-resource', 'detail-page', 'frontend', 'safety'],
    ],
];

$sampleErrors = basic_validate_definition($sample);

$changedFiles = git_changed_files($root);
$allowedChangedFiles = [
    'docs/ai/03-architecture/module-builder-mvp-schema.md',
    'docs/ai/05-rag/contracts/module-builder-mvp-schema.json',
    'docs/ai/04-docops/history/2026-06-30-module-builder-mvp-schema-and-dry-run.md',
    'patches/verify_module_builder_mvp_schema.php',
];

$unexpectedChangedFiles = array_values(array_filter(
    $changedFiles,
    fn (string $file): bool => ! in_array($file, $allowedChangedFiles, true)
));

$unsafeCoreChanges = array_values(array_filter($changedFiles, fn (string $file): bool => str_starts_with($file, 'modules/Core/')));
$unsafeRuntimeModuleChanges = array_values(array_filter($changedFiles, fn (string $file): bool => str_starts_with($file, 'modules/')));
$unsafeMigrationChanges = array_values(array_filter($changedFiles, fn (string $file): bool => str_contains($file, '/database/migrations/')));
$unsafeVendorBuildChanges = array_values(array_filter($changedFiles, fn (string $file): bool => str_starts_with($file, 'vendor/') || str_starts_with($file, 'node_modules/') || str_starts_with($file, 'public/build/')));
$unsafeManifestChanges = array_values(array_filter($changedFiles, fn (string $file): bool => in_array($file, ['composer.json', 'composer.lock', 'package.json', 'package-lock.json'], true)));
$commandFiles = array_values(array_filter($changedFiles, fn (string $file): bool => str_contains($file, 'Command.php') || $file === 'app/Console/Kernel.php'));

$commandImplemented = false;

$failed = false;

echo 'ERPSMART Module Builder MVP Schema Verifier'.PHP_EOL.PHP_EOL;

$failed = ! check('schema_docs_exist', is_file($docsPath)) || $failed;
$failed = ! check('json_schema_exists', is_file($schemaPath)) || $failed;
$failed = ! check('json_schema_valid_json', is_array($schema)) || $failed;
$failed = ! check('history_note_exists', is_file($historyPath)) || $failed;

if (is_array($schema)) {
    foreach (['schemaVersion', 'module', 'resource', 'fields', 'capabilities', 'permissions', 'frontend', 'verifier'] as $key) {
        $failed = ! check('schema_required_key_'.$key, array_key_exists($key, $schema['properties'] ?? [])) || $failed;
    }

    foreach (['tableable', 'customFields', 'uniqueCustomFields', 'importable', 'exportable', 'cloneable', 'mediable', 'notes', 'activities', 'activityComments', 'activityAssociations', 'globalSearch', 'quickCreate', 'bulkDelete', 'softDeletes', 'timeline'] as $flag) {
        $failed = ! check('schema_capability_flag_'.$flag, isset($schema['properties']['capabilities']['properties'][$flag])) || $failed;
    }
}

$failed = ! check('warehouse_like_sample_basic_validation', $sampleErrors === []) || $failed;
$failed = ! check('dry_run_command_not_implemented_in_this_phase', ! $commandImplemented && $commandFiles === []) || $failed;
$failed = ! check('no_unexpected_changed_files', $unexpectedChangedFiles === []) || $failed;
$failed = ! check('no_runtime_module_source_changed', $unsafeRuntimeModuleChanges === []) || $failed;
$failed = ! check('no_core_files_changed', $unsafeCoreChanges === []) || $failed;
$failed = ! check('no_migrations_changed', $unsafeMigrationChanges === []) || $failed;
$failed = ! check('no_vendor_node_build_files_changed', $unsafeVendorBuildChanges === []) || $failed;
$failed = ! check('no_package_or_composer_files_changed', $unsafeManifestChanges === []) || $failed;

if ($sampleErrors !== []) {
    echo PHP_EOL.'Sample validation errors:'.PHP_EOL;
    foreach ($sampleErrors as $error) {
        echo '- '.$error.PHP_EOL;
    }
}

if ($unexpectedChangedFiles !== []) {
    echo PHP_EOL.'Unexpected changed files:'.PHP_EOL;
    foreach ($unexpectedChangedFiles as $file) {
        echo '- '.$file.PHP_EOL;
    }
}

echo PHP_EOL.($failed ? 'FAIL' : 'PASS').PHP_EOL;

exit($failed ? 1 : 0);
