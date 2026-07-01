<?php

$root = dirname(__DIR__);
$definition = 'docs/ai/05-rag/examples/warehouse-like-module-definition.json';
$previewRoot = $root.'/storage/app/module-builder-preview/Inventory';
$checks = [];

function check_result(array &$checks, string $label, bool $passed, string $detail = ''): void
{
    $checks[] = [$label, $passed, $detail];
    echo ($passed ? 'true ' : 'false ').$label.($detail !== '' ? ' - '.$detail : '').PHP_EOL;
}

function run_command(string $root, string $command): array
{
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);

    if (! is_resource($process)) {
        return [1, 'Unable to start command'];
    }

    $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [proc_close($process), $output];
}

function remove_directory(string $path): void
{
    if (! is_dir($path)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($path);
}

function files_under(string $path): array
{
    if (! is_dir($path)) {
        return [];
    }

    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $item) {
        if ($item->isFile()) {
            $files[] = $item->getPathname();
        }
    }

    return $files;
}

remove_directory($previewRoot);

[$dryCode, $dryOutput] = run_command(
    $root,
    'php artisan erpsmart:make-module --definition='.escapeshellarg($definition).' --dry-run'
);

check_result($checks, 'dry-run still passes', $dryCode === 0);
check_result($checks, 'dry-run still reports zero writes', str_contains($dryOutput, 'Writes performed: 0'));

[$previewCode, $previewOutput] = run_command(
    $root,
    'php artisan erpsmart:make-module --definition='.escapeshellarg($definition).' --preview'
);

check_result($checks, 'preview command passes', $previewCode === 0);
check_result($checks, 'preview reports real runtime writes as zero', str_contains($previewOutput, 'Real runtime writes performed: 0'));
check_result($checks, 'preview directory exists', is_dir($previewRoot));

$allPreviewFiles = files_under($previewRoot);
$realPreviewRoot = realpath($previewRoot) ?: $previewRoot;
$onlyPreview = $allPreviewFiles !== [];

foreach ($allPreviewFiles as $file) {
    $realFile = realpath($file) ?: $file;

    if (! str_starts_with($realFile, $realPreviewRoot.DIRECTORY_SEPARATOR)) {
        $onlyPreview = false;
        break;
    }
}

check_result($checks, 'preview files are only under storage/app/module-builder-preview', $onlyPreview);

$model = file_get_contents($previewRoot.'/modules/Inventory/app/Models/Item.php') ?: '';
$migration = file_get_contents($previewRoot.'/modules/Inventory/database/migrations/create_items_table.php') ?: '';
$resource = file_get_contents($previewRoot.'/modules/Inventory/app/Resources/Item.php') ?: '';
$jsonResource = file_get_contents($previewRoot.'/modules/Inventory/app/Http/Resources/ItemResource.php') ?: '';
$command = file_get_contents($root.'/app/Console/Commands/ErpsmartMakeModuleCommand.php') ?: '';

check_result($checks, 'generated Model fillable includes sample text field', str_contains($model, "'name',"));
check_result($checks, 'generated Model fillable includes sample textarea field', str_contains($model, "'description',"));
check_result($checks, 'generated Model fillable includes sample integer field', str_contains($model, "'stock_quantity',"));
check_result($checks, 'generated Model fillable includes sample decimal field', str_contains($model, "'unit_cost',"));
check_result($checks, 'generated Model casts boolean field', str_contains($model, "'is_active' => 'boolean',"));
check_result($checks, 'generated Model casts integer field', str_contains($model, "'stock_quantity' => 'integer',"));
check_result($checks, 'generated Model casts decimal field', str_contains($model, "'unit_cost' => 'decimal:2',"));

check_result($checks, 'generated Migration renders text field length from rules', str_contains($migration, "\$table->string('name', 191);"));
check_result($checks, 'generated Migration renders unique nullable code field', str_contains($migration, "\$table->string('code', 64)->nullable()->unique();"));
check_result($checks, 'generated Migration renders textarea field', str_contains($migration, "\$table->text('description')->nullable();"));
check_result($checks, 'generated Migration renders boolean default', str_contains($migration, "\$table->boolean('is_active')->nullable()->default(true);"));
check_result($checks, 'generated Migration renders integer field', str_contains($migration, "\$table->integer('stock_quantity')->nullable()->default(0);"));
check_result($checks, 'generated Migration renders decimal field', str_contains($migration, "\$table->decimal('unit_cost', 15, 2)->nullable()->default(0);"));

check_result($checks, 'generated Resource imports Textarea field class', str_contains($resource, 'use Modules\\Core\\Fields\\Textarea;'));
check_result($checks, 'generated Resource imports Number field class', str_contains($resource, 'use Modules\\Core\\Fields\\Number;'));
check_result($checks, 'generated Resource renders primary required text field', str_contains($resource, "Text::make('name', 'Name')->primary()->required(true),"));
check_result($checks, 'generated Resource renders textarea field', str_contains($resource, "Textarea::make('description', 'Description'),"));
check_result($checks, 'generated Resource renders integer number field', str_contains($resource, "Number::make('stock_quantity', 'Stock Quantity'),"));
check_result($checks, 'generated Resource renders decimal number field', str_contains($resource, "Number::make('unit_cost', 'Unit Cost'),"));

check_result($checks, 'generated JsonResource includes text field', str_contains($jsonResource, "'name' => \$this->name,"));
check_result($checks, 'generated JsonResource includes boolean cast', str_contains($jsonResource, "'is_active' => (bool) \$this->is_active,"));
check_result($checks, 'generated JsonResource includes integer cast', str_contains($jsonResource, "'stock_quantity' => \$this->stock_quantity === null ? null : (int) \$this->stock_quantity,"));
check_result($checks, 'generated JsonResource includes decimal cast', str_contains($jsonResource, "'unit_cost' => \$this->unit_cost === null ? null : (float) \$this->unit_cost,"));

check_result($checks, 'generated panel id is entity-derived for Item preview', str_contains($resource, "Panel::make('item-detail-panel', 'resource-details-panel')"));
check_result($checks, 'renderer derives panel id from entity name', str_contains($command, "Str::kebab(\$entity).'-detail-panel'"));

check_result($checks, 'no real modules/Inventory directory exists', ! is_dir($root.'/modules/Inventory'));
check_result($checks, 'no real generated inventory verifier exists', ! is_file($root.'/patches/verify_inventory_item_contract.php'));

[$statusCode, $statusOutput] = run_command(
    $root,
    'git -c safe.directory='.escapeshellarg($root).' status --porcelain --untracked-files=all'
);

$changedFiles = array_filter(array_map(
    fn (string $line): string => trim(substr($line, 3)),
    explode(PHP_EOL, trim($statusOutput))
));

$forbiddenPatterns = [
    '#^modules/Warehouse/#',
    '#^modules/Core/#',
    '#^modules/Activities/#',
    '#^modules/Notes/#',
    '#^modules/Inventory/#',
    '#^database/migrations/#',
    '#^vendor/#',
    '#^node_modules/#',
    '#^public/build/#',
    '#^package\.json$#',
    '#^package-lock\.json$#',
    '#^composer\.json$#',
    '#^composer\.lock$#',
];

$forbiddenChanged = [];

foreach ($changedFiles as $file) {
    foreach ($forbiddenPatterns as $pattern) {
        if (preg_match($pattern, $file) === 1) {
            $forbiddenChanged[] = $file;
            break;
        }
    }
}

check_result($checks, 'git status read succeeds', $statusCode === 0);
check_result($checks, 'no forbidden runtime/source paths changed', $forbiddenChanged === [], implode(', ', array_unique($forbiddenChanged)));

$passed = array_reduce($checks, fn (bool $carry, array $check): bool => $carry && $check[1], true);

echo $passed ? 'PASS'.PHP_EOL : 'FAIL'.PHP_EOL;

exit($passed ? 0 : 1);
