<?php

$root = dirname(__DIR__);
$checks = [];
$schemaPath = $root.'/docs/ai/05-rag/contracts/module-builder-mvp-schema.json';
$customDefinition = 'docs/ai/05-rag/examples/definition-driven-custom-module.json';
$relatedDefinition = 'docs/ai/05-rag/examples/custom-related-module-definition.json';
$previewBase = $root.'/storage/app/module-builder-preview';

function dd_check(array &$checks, string $label, bool $passed, string $detail = ''): void
{
    $checks[] = [$label, $passed, $detail];
    echo ($passed ? 'true ' : 'false ').$label.($detail !== '' ? ' - '.$detail : '').PHP_EOL;
}

function dd_run(string $root, string $command): array
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

function dd_remove_dir(string $path): void
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

function dd_files_under(string $path): array
{
    if (! is_dir($path)) {
        return [];
    }

    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $item) {
        if ($item->isFile()) {
            $files[] = $item->getPathname();
        }
    }

    sort($files);

    return $files;
}

function dd_json_file(string $path): ?array
{
    if (! is_file($path)) {
        return null;
    }

    $decoded = json_decode(file_get_contents($path) ?: '', true);

    return is_array($decoded) ? $decoded : null;
}

[$commandLintCode] = dd_run($root, 'php -l app/Console/Commands/ErpsmartMakeModuleCommand.php');
dd_check($checks, 'command PHP syntax is valid', $commandLintCode === 0);

$schema = dd_json_file($schemaPath);
dd_check($checks, 'schema JSON is valid', $schema !== null, json_last_error_msg());
dd_check($checks, 'schema supports capabilities.hasDetailView', isset($schema['properties']['capabilities']['properties']['hasDetailView']));
dd_check($checks, 'schema supports capabilities.media', isset($schema['properties']['capabilities']['properties']['media']));
dd_check($checks, 'schema supports future capabilities', isset($schema['properties']['capabilities']['properties']['documents'], $schema['properties']['capabilities']['properties']['workflow'], $schema['properties']['capabilities']['properties']['conditionalVisibility']));
dd_check($checks, 'schema supports relations array', isset($schema['properties']['relations']));
dd_check($checks, 'schema relation contract prefers targetModel', isset($schema['properties']['relations']['items']['properties']['targetModel']));
dd_check($checks, 'schema does not define ERP pack schema', ! str_contains(file_get_contents($schemaPath) ?: '', 'packs'));

$customJson = dd_json_file($root.'/'.$customDefinition);
$relatedJson = dd_json_file($root.'/'.$relatedDefinition);
dd_check($checks, 'neutral custom module definition exists and is valid JSON', $customJson !== null);
dd_check($checks, 'related neutral module definition exists and is valid JSON', $relatedJson !== null);

dd_remove_dir($previewBase.'/CustomRecords');
dd_remove_dir($previewBase.'/RelatedEntries');

[$customPreviewCode, $customPreviewOutput] = dd_run(
    $root,
    'php artisan erpsmart:make-module --definition='.escapeshellarg($customDefinition).' --preview'
);
[$relatedPreviewCode, $relatedPreviewOutput] = dd_run(
    $root,
    'php artisan erpsmart:make-module --definition='.escapeshellarg($relatedDefinition).' --preview'
);

dd_check($checks, 'neutral custom module preview succeeds', $customPreviewCode === 0, trim($customPreviewOutput));
dd_check($checks, 'related neutral module preview succeeds', $relatedPreviewCode === 0, trim($relatedPreviewOutput));
dd_check($checks, 'custom preview reports zero runtime writes', str_contains($customPreviewOutput, 'Real runtime writes performed: 0'));
dd_check($checks, 'related preview reports zero runtime writes', str_contains($relatedPreviewOutput, 'Real runtime writes performed: 0'));

$previewFiles = array_merge(dd_files_under($previewBase.'/CustomRecords'), dd_files_under($previewBase.'/RelatedEntries'));
$realPreviewBase = realpath($previewBase) ?: $previewBase;
$onlyUnderPreview = $previewFiles !== [];

foreach ($previewFiles as $file) {
    $realFile = realpath($file) ?: $file;

    if (! str_starts_with($realFile, $realPreviewBase.DIRECTORY_SEPARATOR)) {
        $onlyUnderPreview = false;
        break;
    }
}

dd_check($checks, 'preview files are only under storage/app/module-builder-preview', $onlyUnderPreview);

$customRoot = $previewBase.'/CustomRecords/modules/CustomRecords';
$relatedRoot = $previewBase.'/RelatedEntries/modules/RelatedEntries';
$customModel = file_get_contents($customRoot.'/app/Models/CustomRecord.php') ?: '';
$customMigration = file_get_contents($customRoot.'/database/migrations/create_custom_records_table.php') ?: '';
$customResource = file_get_contents($customRoot.'/app/Resources/CustomRecord.php') ?: '';
$customJsonResource = file_get_contents($customRoot.'/app/Http/Resources/CustomRecordResource.php') ?: '';
$relatedModel = file_get_contents($relatedRoot.'/app/Models/RelatedEntry.php') ?: '';
$relatedMigration = file_get_contents($relatedRoot.'/database/migrations/create_related_entries_table.php') ?: '';
$command = file_get_contents($root.'/app/Console/Commands/ErpsmartMakeModuleCommand.php') ?: '';

foreach (['title', 'reference_number', 'long_text', 'active', 'amount', 'sort_order', 'due_date', 'import_id'] as $field) {
    dd_check($checks, 'CustomRecord fillable contains '.$field, str_contains($customModel, "'{$field}',"));
}

foreach (['name', 'code', 'sku', 'price', 'stock', 'stock_quantity', 'unit_cost', 'warehouse_id', 'product_id', 'inventory_id'] as $forbiddenField) {
    dd_check($checks, 'CustomRecord model does not contain forbidden field '.$forbiddenField, ! str_contains($customModel, "'{$forbiddenField}'"));
}

dd_check($checks, 'CustomRecord migration contains title field', str_contains($customMigration, "\$table->string('title', 191);"));
dd_check($checks, 'CustomRecord migration contains reference_number field', str_contains($customMigration, "\$table->string('reference_number', 80)->nullable();"));
dd_check($checks, 'CustomRecord migration contains long_text field', str_contains($customMigration, "\$table->text('long_text')->nullable();"));
dd_check($checks, 'CustomRecord migration contains amount field', str_contains($customMigration, "\$table->decimal('amount', 15, 2)->nullable()->default(0);"));
dd_check($checks, 'CustomRecord migration contains due_date field', str_contains($customMigration, "\$table->date('due_date')->nullable();"));

foreach (['warehouse', 'product', 'inventory', 'sku', 'stock'] as $forbiddenWord) {
    dd_check($checks, 'CustomRecord migration avoids '.$forbiddenWord, stripos($customMigration, $forbiddenWord) === false);
}

dd_check($checks, 'CustomRecord Resource fields are definition-driven', str_contains($customResource, "Text::make('title', 'Title')->primary()->required(true),") && str_contains($customResource, "Textarea::make('long_text', 'Long Text'),") && str_contains($customResource, "Date::make('due_date', 'Due Date'),"));
dd_check($checks, 'CustomRecord Resource has notes tab only because notes enabled', str_contains($customResource, "Tab::make('notes', 'notes-tab')"));
dd_check($checks, 'CustomRecord Resource has activities tab only because activities enabled', str_contains($customResource, "Tab::make('activities', 'activities-tab')"));
dd_check($checks, 'CustomRecord Resource has media panel only because media enabled', str_contains($customResource, "Panel::make('media', 'resource-media-panel')"));
dd_check($checks, 'CustomRecord JsonResource is definition-driven', str_contains($customJsonResource, "'title' => \$this->title,") && str_contains($customJsonResource, "'amount' => \$this->amount === null ? null : (float) \$this->amount,") && str_contains($customJsonResource, "'due_date' => \$this->due_date,"));

dd_check($checks, 'RelatedEntry migration contains declared foreign key', str_contains($relatedMigration, "\$table->unsignedBigInteger('custom_record_id');"));
dd_check($checks, 'RelatedEntry model imports targetModel', str_contains($relatedModel, 'use Modules\\CustomRecords\\Models\\CustomRecord;'));
dd_check($checks, 'RelatedEntry model has declared belongsTo relation', str_contains($relatedModel, 'public function customRecord(): BelongsTo') && str_contains($relatedModel, "return \$this->belongsTo(CustomRecord::class, 'custom_record_id');"));
dd_check($checks, 'relation code reads targetModel contract', str_contains($command, "\$relation['targetModel'] ?? \$relation['relatedModel'] ?? null"));

dd_check($checks, 'no real CustomRecords module exists', ! is_dir($root.'/modules/CustomRecords'));
dd_check($checks, 'no real RelatedEntries module exists', ! is_dir($root.'/modules/RelatedEntries'));
dd_check($checks, 'no real generated custom verifier exists', ! is_file($root.'/patches/verify_custom-records_custom-record_contract.php'));
dd_check($checks, 'no real generated related verifier exists', ! is_file($root.'/patches/verify_related-entries_related-entry_contract.php'));

[$statusCode, $statusOutput] = dd_run(
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
    '#^modules/CustomRecords/#',
    '#^modules/RelatedEntries/#',
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

dd_check($checks, 'git status read succeeds', $statusCode === 0);
dd_check($checks, 'no forbidden runtime/source paths changed', $forbiddenChanged === [], implode(', ', array_unique($forbiddenChanged)));

$passed = array_reduce($checks, fn (bool $carry, array $check): bool => $carry && $check[1], true);

echo $passed ? 'PASS'.PHP_EOL : 'FAIL'.PHP_EOL;

exit($passed ? 0 : 1);
