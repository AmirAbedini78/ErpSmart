<?php
/**
 * Fix Warehouse Attachments panel frontend contract.
 *
 * ResourceMediaPanel is a Core resource-panel component, not only a plain prop-driven
 * component. It expects the same panel/record context that Core's generic detail
 * renderer normally provides. Warehouse uses a custom detail view, so we provide
 * the missing contract explicitly.
 *
 * Run from project root:
 *   docker compose exec app php patches/apply_warehouse_attachments_panel_contract_fix.php
 */

function fail_step(string $message): never
{
    fwrite(STDERR, "ERROR: {$message}\n");
    exit(1);
}

function read_file_or_fail(string $path): string
{
    if (! is_file($path)) {
        fail_step("File not found: {$path}");
    }

    $contents = file_get_contents($path);

    if ($contents === false) {
        fail_step("Unable to read file: {$path}");
    }

    return $contents;
}

function write_if_changed(string $path, string $old, string $new): void
{
    if ($old === $new) {
        echo "No changes needed: {$path}\n";
        return;
    }

    $backup = $path.'.bak-attachments-panel-contract-'.date('YmdHis');

    if (! copy($path, $backup)) {
        fail_step("Unable to create backup: {$backup}");
    }

    if (file_put_contents($path, $new) === false) {
        fail_step("Unable to write file: {$path}");
    }

    echo "Backup created: {$backup}\n";
    echo "Updated: {$path}\n";
}

$viewPath = 'modules/Warehouse/resources/js/views/WarehousesView.vue';
$view = read_file_or_fail($viewPath);
$viewNew = $view;

// 1) ResourceMediaPanel must receive a panel object, because the Core panel
// renderer normally passes Panel metadata. Without it, the component may read
// panel.id while panel is undefined.
$viewNew = preg_replace(
    '/<ResourceMediaPanel\s+(?![^>]*:panel=)/s',
    '<ResourceMediaPanel' . "\n              :panel=\"attachmentsPanel\"" . "\n              ",
    $viewNew,
    1
) ?? $viewNew;

// 2) Guard rendering until the record id is present.
$viewNew = preg_replace(
    '/<ResourceMediaPanel\s+(?![^>]*v-if=)/s',
    '<ResourceMediaPanel' . "\n              v-if=\"safeResource.id\"" . "\n              ",
    $viewNew,
    1
) ?? $viewNew;

// 3) Add panel metadata in script setup.
if (! str_contains($viewNew, 'const attachmentsPanel = computed(')) {
    $anchor = <<<'JS'
const hasIsActiveStatus = computed(() =>
  Object.prototype.hasOwnProperty.call(safeResource.value, 'is_active')
)
JS;

    if (! str_contains($viewNew, $anchor)) {
        fail_step('Could not locate hasIsActiveStatus computed anchor. Please send WarehousesView.vue.');
    }

    $addition = <<<'JS'

const attachmentsPanel = computed(() => ({
  id: 'warehouse-media',
  component: 'resource-media-panel',
  name: 'media',
  heading: Innoclapps.t ? Innoclapps.t('core::app.attachments') : 'Attachments',
}))
JS;

    $viewNew = str_replace($anchor, $anchor.$addition, $viewNew);
}

// 4) Provide the same record/resource context that Core panel components expect
// in the generic record view. Keep the existing explicit props too.
$provideAnchor = <<<'JS'
provide('decrementResourceCount', decrementResourceCount)
JS;

if (! str_contains($viewNew, "provide('record', safeResource)")) {
    if (! str_contains($viewNew, $provideAnchor)) {
        fail_step('Could not locate provide anchor. Please send WarehousesView.vue.');
    }

    $viewNew = str_replace(
        $provideAnchor,
        $provideAnchor."\nprovide('record', safeResource)\nprovide('resource', safeResource)\nprovide('resourceName', resourceName)\nprovide('resourceId', warehouseId)",
        $viewNew
    );
}

// 5) Ensure media defaults are still normalized.
if (! str_contains($viewNew, 'media: Array.isArray(value.media) ? value.media : []')) {
    $anchor = "    notes_count: Number(value.notes_count || 0),\n";

    if (! str_contains($viewNew, $anchor)) {
        fail_step('Could not locate notes_count normalization anchor. Please send normalizeResource() block.');
    }

    $viewNew = str_replace(
        $anchor,
        $anchor."\n    media: Array.isArray(value.media) ? value.media : [],\n    media_count: Number(value.media_count || (Array.isArray(value.media) ? value.media.length : 0)),\n",
        $viewNew
    );
}

write_if_changed($viewPath, $view, $viewNew);

echo "\nWarehouse attachments panel contract fix applied.\n";
echo "Next commands:\n";
echo "  php artisan optimize:clear\n";
echo "  rm -f public/hot\n";
echo "  npm run build\n";
