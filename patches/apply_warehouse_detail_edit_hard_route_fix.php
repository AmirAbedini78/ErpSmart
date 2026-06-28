<?php

/**
 * Warehouse detail edit hard route fix.
 *
 * This patch intentionally removes the inline-edit experiment from the detail page and
 * makes the Edit action use a canonical full page URL: /warehouses/{id}/edit.
 * It also rewrites the Warehouse router order so edit is matched before view/index fallbacks.
 */

function fail(string $message): never
{
    fwrite(STDERR, "[warehouse-detail-edit-hard-route-fix] ERROR: {$message}\n");
    exit(1);
}

function write_backup(string $file): void
{
    if (! is_file($file)) {
        fail("Missing file: {$file}");
    }

    $backup = $file.'.bak-detail-edit-hard-route-'.date('YmdHis');
    if (! copy($file, $backup)) {
        fail("Could not create backup: {$backup}");
    }

    echo "Backup created: {$backup}\n";
}

$view = base_path('modules/Warehouse/resources/js/views/WarehousesView.vue');
$routes = base_path('modules/Warehouse/resources/js/routes.js');

write_backup($view);
write_backup($routes);

$viewContent = file_get_contents($view);

// Remove inline edit component import and nextTick-only import usage.
$viewContent = str_replace("import WarehousesEdit from './WarehousesEdit.vue'\n", '', $viewContent);
$viewContent = str_replace("import { computed, provide, watch, ref, nextTick } from 'vue'", "import { computed, provide, watch } from 'vue'", $viewContent);
$viewContent = str_replace("import { computed, provide, watch, ref } from 'vue'", "import { computed, provide, watch } from 'vue'", $viewContent);

// Remove Teleport/WarehousesEdit block if present.
$viewContent = preg_replace(
    '#\n\s*<Teleport to="body">\s*<WarehousesEdit[\s\S]*?</Teleport>\s*#',
    "\n",
    $viewContent
);
$viewContent = preg_replace(
    '#\n\s*<WarehousesEdit[\s\S]*?</WarehousesEdit>\s*#',
    "\n",
    $viewContent
);

// Replace actions area with stable native buttons. This replaces the whole NavbarItems block.
$actionsReplacement = <<<'VUE'
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
          data-warehouse-edit-button="navbar"
          @click.stop.prevent="goToEditPage"
        >
          <Icon icon="PencilSquareSolid" class="size-4" />
          <span>{{ editButtonText }}</span>
        </button>
      </NavbarItems>
VUE;

$viewContent = preg_replace('#\s*<NavbarItems>[\s\S]*?</NavbarItems>#', "\n".$actionsReplacement, $viewContent, 1, $count);
if ($count !== 1) {
    fail('Could not replace NavbarItems block in WarehousesView.vue');
}

// Ensure a second body edit button exists outside Navbar so click is not blocked by header slots.
if (! str_contains($viewContent, 'data-warehouse-edit-button="body"')) {
    $bodyEdit = <<<'VUE'
      <div class="mb-3 flex justify-end">
        <button
          v-if="safeResource.authorizations.update"
          type="button"
          class="inline-flex items-center gap-1.5 rounded-md bg-primary-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
          data-warehouse-edit-button="body"
          @click.stop.prevent="goToEditPage"
        >
          <Icon icon="PencilSquareSolid" class="size-4" />
          <span>{{ editButtonText }}</span>
        </button>
      </div>

VUE;
    $viewContent = preg_replace('#(<div v-if="componentReady" class="mx-auto max-w-5xl">\s*)#', "$1\n".$bodyEdit, $viewContent, 1, $count);
    if ($count !== 1) {
        fail('Could not insert body edit button in WarehousesView.vue');
    }
}

// Remove old inline edit functions/state blocks.
$viewContent = preg_replace('#\nconst showInlineEditForm = ref\(false\)[\s\S]*?async function handleInlineEditUpdated\(updatedWarehouse\) \{[\s\S]*?await fetchResource\(\)\n\}\s*#', "\n", $viewContent);
$viewContent = preg_replace('#\nfunction openInlineEditForm\(\) \{[\s\S]*?\}\s*#', "\n", $viewContent);

// Ensure text helpers and navigation functions exist and are stable.
if (! str_contains($viewContent, 'function normalizeTranslationText(')) {
    $helperBlock = <<<'JS'
function normalizeTranslationText(value, fallback) {
  if (typeof value === 'string') {
    return value
  }

  if (value && typeof value === 'object') {
    if (typeof value.name === 'string') return value.name
    if (typeof value.label === 'string') return value.label
    if (typeof value.text === 'string') return value.text
  }

  return fallback
}

JS;
    $viewContent = preg_replace('#(const resourcePath = computed\(\(\) => `\/\$\{resourceName\}\/\$\{warehouseId\.value\}`\)\s*)#', "$1\n".$helperBlock, $viewContent, 1, $count);
    if ($count !== 1) {
        fail('Could not insert normalizeTranslationText helper.');
    }
}

// Replace old computed text blocks if already exist.
$viewContent = preg_replace('#const backButtonText = computed\(\(\) => \{[\s\S]*?\}\)\s*#', '', $viewContent);
$viewContent = preg_replace('#const editButtonText = computed\(\(\) => \{[\s\S]*?\}\)\s*#', '', $viewContent);

$textAndNavBlock = <<<'JS'
const backButtonText = computed(() =>
  normalizeTranslationText(
    Innoclapps.t ? Innoclapps.t('warehouse::warehouse.back_to_warehouses') : null,
    'Back to warehouses'
  )
)

const editButtonText = computed(() =>
  normalizeTranslationText(
    Innoclapps.t ? Innoclapps.t('core::app.edit') : null,
    'Edit'
  )
)

function goBackToIndex() {
  window.location.assign(`/${resourceName}`)
}

function goToEditPage() {
  window.location.assign(`/${resourceName}/${warehouseId.value}/edit`)
}

JS;

// Remove previous goBackToIndex if present, then insert text/nav block once.
$viewContent = preg_replace('#\nfunction goBackToIndex\(\) \{[\s\S]*?\}\s*#', "\n", $viewContent);
$viewContent = preg_replace('#\nfunction goToEditPage\(\) \{[\s\S]*?\}\s*#', "\n", $viewContent);
$viewContent = preg_replace('#(function normalizeTranslationText\([\s\S]*?\n\}\n\n)#', "$1".$textAndNavBlock, $viewContent, 1, $count);
if ($count !== 1) {
    fail('Could not insert text/nav functions.');
}

// Clean duplicate blank lines mildly.
$viewContent = preg_replace("/\n{4,}/", "\n\n\n", $viewContent);
file_put_contents($view, $viewContent);

// Rewrite routes to canonical top-level order: create, edit, view, index.
$routesContent = <<<'JS'
const routes = [
  {
    path: '/warehouses/create',
    name: 'warehouses.create',
    component: () => import('./views/WarehousesCreate.vue'),
  },
  {
    path: '/warehouses/:id/edit',
    name: 'warehouses.edit',
    component: () => import('./views/WarehousesEdit.vue'),
    props: true,
  },
  {
    path: '/warehouses/:id',
    name: 'warehouses.view',
    component: () => import('./views/WarehousesView.vue'),
    props: true,
  },
  {
    path: '/warehouses',
    name: 'warehouses.index',
    component: () => import('./views/WarehousesIndex.vue'),
  },
]

export default routes
JS;
file_put_contents($routes, $routesContent);

echo "Warehouse detail edit hard route fix applied.\n";
