<?php

/**
 * Warehouse detail inline edit modal fix.
 *
 * Purpose:
 * Previous detail edit navigation used /warehouses/{id}/edit. In this project,
 * that route is mounted as an index child/slideover and can fall back to the
 * index page when triggered from the custom detail page. This patch makes the
 * detail Edit action open the existing WarehousesEdit slideover inline, without
 * leaving the detail route.
 */

$root = dirname(__DIR__);
$viewPath = $root.'/modules/Warehouse/resources/js/views/WarehousesView.vue';
$editPath = $root.'/modules/Warehouse/resources/js/views/WarehousesEdit.vue';

function fail(string $message): void
{
    fwrite(STDERR, "ERROR: {$message}\n");
    exit(1);
}

function backup(string $path): void
{
    $backup = $path.'.bak-'.date('YmdHis');
    copy($path, $backup);
    echo "Backup created: {$backup}\n";
}

function ensureVueImport(string $contents, string $symbol): string
{
    if (preg_match("/import\\s*\\{([^}]*)\\}\\s*from\\s*['\"]vue['\"]/", $contents, $m)) {
        $imports = array_values(array_filter(array_map('trim', explode(',', $m[1]))));
        if (! in_array($symbol, $imports, true)) {
            $imports[] = $symbol;
        }
        $replacement = 'import { '.implode(', ', array_unique($imports))." } from 'vue'";
        return preg_replace("/import\\s*\\{([^}]*)\\}\\s*from\\s*['\"]vue['\"]/", $replacement, $contents, 1);
    }

    return preg_replace('/(<script\\s+setup[^>]*>\\s*)/', "$1\nimport { {$symbol} } from 'vue'\n", $contents, 1);
}

function ensureRelativeImport(string $contents, string $importLine): string
{
    if (str_contains($contents, $importLine)) {
        return $contents;
    }

    return preg_replace('/(<script\\s+setup[^>]*>\\s*(?:\\nimport[^\\n]*\\n)*)/', "$1{$importLine}\n", $contents, 1);
}

if (! file_exists($viewPath)) {
    fail("View file not found: {$viewPath}");
}

if (! file_exists($editPath)) {
    fail("Edit file not found: {$editPath}");
}

backup($viewPath);
backup($editPath);

// -----------------------------------------------------------------------------
// Patch WarehousesEdit.vue so it can be mounted inline from the detail page.
// -----------------------------------------------------------------------------
$edit = file_get_contents($editPath);
$editOriginal = $edit;

$edit = ensureVueImport($edit, 'computed');

$edit = str_replace(
    "const emit = defineEmits(['updated'])",
    "const emit = defineEmits(['updated', 'closed'])",
    $edit
);

if (! preg_match('/const\\s+props\\s*=\\s*defineProps\\s*\\(/', $edit)) {
    $propsBlock = <<<'JS'

const props = defineProps({
  recordId: { type: [String, Number], default: null },
  redirectOnClose: { type: Boolean, default: true },
})
JS;

    $edit = preg_replace(
        "/const emit = defineEmits\\(\\['updated'(?:, 'closed')?\\]\\)\\s*/",
        "$0".$propsBlock."\n",
        $edit,
        1
    );
}

$edit = str_replace('@hidden="$router.back"', '@hidden="handleHidden"', $edit);
$edit = str_replace(":resource-id=\"$route.params.id\"", ':resource-id="currentWarehouseId"', $edit);

if (! preg_match('/const\\s+currentWarehouseId\\s*=\\s*computed/', $edit)) {
    $currentIdBlock = <<<'JS'

const currentWarehouseId = computed(() => props.recordId || route.params.id)

function handleHidden() {
  if (props.redirectOnClose) {
    router.back()
    return
  }

  emit('closed')
}
JS;

    if (preg_match('/const\\s+warehouse\\s*=\\s*ref\\(\\{\\}\\)\\s*/', $edit)) {
        $edit = preg_replace('/(const\\s+warehouse\\s*=\\s*ref\\(\\{\\}\\)\\s*)/', "$1".$currentIdBlock."\n", $edit, 1);
    } else {
        $edit = preg_replace('/(<script\\s+setup[^>]*>\\s*(?:\\nimport[^\\n]*\\n)*)/', "$1".$currentIdBlock."\n", $edit, 1);
    }
}

$edit = str_replace('updateResource(form, route.params.id)', 'updateResource(form, currentWarehouseId.value)', $edit);
$edit = str_replace('retrieveResource(route.params.id)', 'retrieveResource(currentWarehouseId.value)', $edit);
$edit = str_replace('getUpdateFields(resourceName, route.params.id)', 'getUpdateFields(resourceName, currentWarehouseId.value)', $edit);

$edit = preg_replace(
    "/Innoclapps\\.success\\(t\\('warehouse::warehouse.updated'\\)\\)\\s*\\n\\s*router\\.back\\(\\)/",
    "Innoclapps.success(t('warehouse::warehouse.updated'))\n\n  if (props.redirectOnClose) {\n    router.back()\n  } else {\n    emit('closed')\n  }",
    $edit,
    1
);

if ($edit !== $editOriginal) {
    file_put_contents($editPath, $edit);
    echo "Patched WarehousesEdit.vue for inline mounting.\n";
} else {
    echo "WarehousesEdit.vue already compatible or no textual change required.\n";
}

// -----------------------------------------------------------------------------
// Patch WarehousesView.vue to open WarehousesEdit inline instead of navigating.
// -----------------------------------------------------------------------------
$view = file_get_contents($viewPath);
$viewOriginal = $view;

$view = ensureVueImport($view, 'ref');
$view = ensureRelativeImport($view, "import WarehousesEdit from './WarehousesEdit.vue'");

// Remove old router/editPath helpers if present from previous route-based fix.
$view = preg_replace('/\\nconst\\s+editPath\\s*=\\s*computed\\([^\\n]*\\)\\s*\\n/s', "\n", $view, 1);
$view = preg_replace('/\\nfunction\\s+goToEdit\\s*\\(\\)\\s*\\{[\\s\\S]*?router\\.push\\([^}]*?\\n\\}/', "\n", $view, 1);

if (! preg_match('/const\\s+showInlineEditForm\\s*=\\s*ref\\(false\\)/', $view)) {
    $stateBlock = <<<'JS'

const showInlineEditForm = ref(false)

function openInlineEditForm() {
  showInlineEditForm.value = true
}

async function handleInlineEditUpdated(updatedWarehouse) {
  synchronizeResource(updatedWarehouse, true)
  showInlineEditForm.value = false
  await fetchResource()
}
JS;

    if (preg_match('/const\\s+resourcePath\\s*=\\s*computed\\([^\\n]*\\)\\s*/', $view)) {
        $view = preg_replace('/(const\\s+resourcePath\\s*=\\s*computed\\([^\\n]*\\)\\s*)/', "$1".$stateBlock."\n", $view, 1);
    } elseif (preg_match('/const\\s+warehouseId\\s*=\\s*computed\\([^\\n]*\\)\\s*/', $view)) {
        $view = preg_replace('/(const\\s+warehouseId\\s*=\\s*computed\\([^\\n]*\\)\\s*)/', "$1".$stateBlock."\n", $view, 1);
    } else {
        $view = preg_replace('/(<script\\s+setup[^>]*>\\s*(?:\\nimport[^\\n]*\\n)*)/', "$1".$stateBlock."\n", $view, 1);
    }
}

// Change the visible edit button action to open the inline slideover.
$patchedEditButton = false;
$view = preg_replace_callback(
    '/<IButton\\b[\\s\\S]*?<\\/IButton>/i',
    function ($matches) use (&$patchedEditButton) {
        $block = $matches[0];

        if ($patchedEditButton) {
            return $block;
        }

        $looksLikeEdit = str_contains($block, 'core::app.edit')
            || str_contains($block, 'warehouse.edit')
            || preg_match('/>\\s*(Edit|ویرایش)\\s*</iu', $block);

        if (! $looksLikeEdit) {
            return $block;
        }

        $block = preg_replace('/\\s+:to="[^"]*"/i', '', $block);
        $block = preg_replace('/\\s+to="[^"]*"/i', '', $block);
        $block = preg_replace('/\\s+@click(?:\\.prevent)?="[^"]*"/i', '', $block);
        $block = preg_replace('/<IButton\\b/i', '<IButton @click.prevent="openInlineEditForm"', $block, 1);

        $patchedEditButton = true;
        return $block;
    },
    $view,
    1
);

$modalMarkup = <<<'VUE'

    <WarehousesEdit
      v-if="showInlineEditForm"
      :record-id="warehouseId"
      :redirect-on-close="false"
      @updated="handleInlineEditUpdated"
      @closed="showInlineEditForm = false"
    />
VUE;

if (! str_contains($view, '<WarehousesEdit') || ! str_contains($view, 'showInlineEditForm')) {
    if (str_contains($view, '<RouterView')) {
        $view = preg_replace('/\\n\\s*<RouterView\\b/', $modalMarkup."\n\n    <RouterView", $view, 1);
    } else {
        $view = preg_replace('/\\n\\s*<\\/MainLayout>/', $modalMarkup."\n  </MainLayout>", $view, 1);
    }
}

if ($view !== $viewOriginal) {
    file_put_contents($viewPath, $view);
    echo "Patched WarehousesView.vue to open inline edit modal.\n";
} else {
    echo "WarehousesView.vue already compatible or no textual change required.\n";
}

echo "Next commands:\n";
echo "  php artisan optimize:clear\n";
echo "  php artisan cache:clear\n";
echo "  npm run build\n";
