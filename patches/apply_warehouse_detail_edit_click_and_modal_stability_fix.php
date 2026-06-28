<?php

declare(strict_types=1);

$root = getcwd();
$viewPath = $root . '/modules/Warehouse/resources/js/views/WarehousesView.vue';

if (! file_exists($viewPath)) {
    fwrite(STDERR, "Missing file: {$viewPath}\n");
    exit(1);
}

$original = file_get_contents($viewPath);
$contents = $original;
$backup = $viewPath . '.bak-edit-click-modal-' . date('YmdHis');
file_put_contents($backup, $original);

function replace_once(string $contents, string $pattern, string $replacement, string $label): string
{
    $new = preg_replace($pattern, $replacement, $contents, 1, $count);

    if ($new === null) {
        fwrite(STDERR, "Regex failed while replacing {$label}.\n");
        exit(1);
    }

    if ($count < 1) {
        fwrite(STDERR, "Could not locate block for {$label}.\n");
        exit(1);
    }

    return $new;
}

$navbarReplacement = <<<'VUE'
      <NavbarItems>
        <button
          type="button"
          class="inline-flex items-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-100 dark:text-neutral-200 dark:hover:bg-neutral-800"
          @click.stop.prevent="goBackToIndex"
        >
          <Icon icon="ChevronLeft" class="size-4" />
          <span>{{ backButtonText }}</span>
        </button>

        <button
          v-if="safeResource.authorizations.update"
          type="button"
          class="inline-flex items-center gap-1.5 rounded-md bg-primary-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
          @mousedown.stop.prevent
          @click.stop.prevent="openInlineEditForm"
        >
          <Icon icon="PencilSquareSolid" class="size-4" />
          <span>{{ editButtonText }}</span>
        </button>
      </NavbarItems>
VUE;

$contents = replace_once(
    $contents,
    '/\s*<NavbarItems>[\s\S]*?<\/NavbarItems>/',
    "\n" . $navbarReplacement,
    'navbar actions'
);

// Remove the duplicate body-level edit button that had no click handler.
$contents = preg_replace(
    '/\n\s*<IButton\s+class="mb-3"\s+variant="primary"\s*>\s*\n\s*\{\{\s*\$t\(\'core::app\.edit\'\)\s*\}\}\s*\n\s*<\/IButton>\s*/',
    "\n",
    $contents,
    1
) ?? $contents;

// Render the edit slideover through body teleport to avoid layout/slot clipping and remount cleanly.
$teleportBlock = <<<'VUE'
    <Teleport to="body">
      <WarehousesEdit
        v-if="showInlineEditForm"
        :key="`warehouse-inline-edit-${warehouseId}`"
        :record-id="warehouseId"
        :redirect-on-close="false"
        @updated="handleInlineEditUpdated"
        @closed="showInlineEditForm = false"
      />
    </Teleport>
VUE;

if (preg_match('/\s*<WarehousesEdit\s+[\s\S]*?\/>
/', $contents)) {
    $contents = preg_replace('/\s*<WarehousesEdit\s+[\s\S]*?\/>
/', "\n" . $teleportBlock . "\n", $contents, 1) ?? $contents;
} elseif (! str_contains($contents, '<Teleport to="body">') && str_contains($contents, '<RouterView @updated="fetchResource" />')) {
    $contents = str_replace('    <RouterView @updated="fetchResource" />', $teleportBlock . "\n\n    <RouterView @updated=\"fetchResource\" />", $contents);
}

// Ensure nextTick is imported for clean remounting.
$contents = str_replace("import { computed, provide, watch, ref } from 'vue'", "import { computed, provide, watch, ref, nextTick } from 'vue'", $contents);

$scriptInsert = <<<'JS'
const backButtonText = computed(() => {
  const translated = Innoclapps.t
    ? Innoclapps.t('warehouse::warehouse.back_to_warehouses')
    : null

  return typeof translated === 'string' ? translated : 'Back to warehouses'
})

const editButtonText = computed(() => {
  const translated = Innoclapps.t ? Innoclapps.t('core::app.edit') : null

  return typeof translated === 'string' ? translated : 'Edit'
})

function goBackToIndex() {
  router.push(`/${resourceName}`)
}

JS;

if (! str_contains($contents, 'const backButtonText = computed(() =>')) {
    $contents = str_replace("const showInlineEditForm = ref(false)\n\n", $scriptInsert . "const showInlineEditForm = ref(false)\n\n", $contents);
}

$openFunction = <<<'JS'
function openInlineEditForm() {
  showInlineEditForm.value = false

  nextTick(() => {
    showInlineEditForm.value = true
  })
}
JS;

$contents = preg_replace(
    '/function openInlineEditForm\(\)\s*\{\s*showInlineEditForm\.value\s*=\s*true\s*\}/',
    $openFunction,
    $contents,
    1
) ?? $contents;

// Make sure Teleport is not placed inside the componentReady block accidentally by previous edits.
if (! str_contains($contents, '@click.stop.prevent="openInlineEditForm"')) {
    fwrite(STDERR, "Patch did not add stable edit click handler. Restoring backup path: {$backup}\n");
    exit(1);
}

file_put_contents($viewPath, $contents);

echo "Warehouse detail edit click/modal stability fix applied.\n";
echo "Backup: {$backup}\n";
