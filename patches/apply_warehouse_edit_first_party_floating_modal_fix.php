<?php

/**
 * Warehouse Edit First-Party Floating Modal Fix
 *
 * Aligns Warehouse edit behavior with first-party CRM resources:
 * - Contact/Company/Deal resources expose Action::make()->floatResourceInEditMode().
 * - Floating edit is handled through Core's global floating resource modal query contract.
 * - The detail page should call useFloatingResourceModal(), not mount WarehousesEdit directly.
 *
 * This script is intentionally pure PHP and does not use Laravel helpers like base_path().
 */

$root = dirname(__DIR__);

function file_path(string $relative): string
{
    global $root;
    return $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
}

function ensure_dir(string $path): void
{
    $dir = dirname($path);
    if (! is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
}

function read_file(string $relative): string
{
    $path = file_path($relative);
    if (! file_exists($path)) {
        fwrite(STDERR, "Missing file: {$relative}\n");
        exit(1);
    }
    return file_get_contents($path);
}

function write_file(string $relative, string $contents): void
{
    $path = file_path($relative);
    ensure_dir($path);
    file_put_contents($path, $contents);
}

function backup_file(string $relative, string $suffix): void
{
    $path = file_path($relative);
    if (file_exists($path)) {
        copy($path, $path . '.bak-' . $suffix);
    }
}

function replace_or_fail(string $pattern, string $replacement, string $subject, string $message): string
{
    $result = preg_replace($pattern, $replacement, $subject, 1, $count);
    if ($result === null || $count === 0) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
    return $result;
}

$suffix = date('YmdHis');

$viewRelative = 'modules/Warehouse/resources/js/views/WarehousesView.vue';
$resourceRelative = 'modules/Warehouse/app/Resources/Warehouse.php';
$appRelative = 'modules/Warehouse/resources/js/app.js';
$floatingRelative = 'modules/Warehouse/resources/js/components/WarehouseFloatingModal.vue';

backup_file($viewRelative, $suffix);
backup_file($resourceRelative, $suffix);
if (file_exists(file_path($appRelative))) {
    backup_file($appRelative, $suffix);
}
if (file_exists(file_path($floatingRelative))) {
    backup_file($floatingRelative, $suffix);
}

// -----------------------------------------------------------------------------
// 1) Add WarehouseFloatingModal.vue
// -----------------------------------------------------------------------------
$floatingModal = <<<'VUE'
<template>
  <ISlideover
    id="warehouseFloatingModal"
    :ok-disabled="form.busy || (hasFields && !warehouse.authorizations?.update)"
    :ok-loading="form.busy"
    :ok-text="$t('core::app.save')"
    :title="modalTitle"
    visible
    form
    @hidden="handleHidden"
    @submit="update"
  >
    <FieldsPlaceholder v-if="!hasFields" />

    <FormFields
      v-else
      :fields="fields"
      :form="form"
      :resource-name="resourceName"
      :resource-id="currentWarehouseId"
      is-floating
      @update-field-value="form.fill($event.attribute, $event.value)"
      @set-initial-value="form.set($event.attribute, $event.value)"
    />
  </ISlideover>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

import { useForm } from '@/Core/composables/useForm'
import { useResourceable } from '@/Core/composables/useResourceable'
import { useResourceFields } from '@/Core/composables/useResourceFields'

defineOptions({ name: 'WarehouseFloatingModal' })

const emit = defineEmits(['hidden', 'closed', 'updated'])

const props = defineProps({
  mode: { type: String, default: 'edit' },
  resourceId: { type: [String, Number], required: true },
})

const resourceName = Innoclapps.resourceName('warehouses')

const { t } = useI18n()
const { fields, hasFields, getUpdateFields, hydrateFields } = useResourceFields()
const { form } = useForm()
const { retrieveResource, updateResource } = useResourceable(resourceName)

const warehouse = ref({ authorizations: { update: true } })

const currentWarehouseId = computed(() => props.resourceId)

const modalTitle = computed(() => {
  const label = warehouse.value?.display_name || warehouse.value?.name || ''

  return label
    ? `${t('core::app.edit')} ${label}`
    : t('warehouse::warehouse.edit')
})

function handleHidden() {
  emit('hidden')
  emit('closed')
}

async function update() {
  const updatedWarehouse = await updateResource(form, currentWarehouseId.value)

  emit('updated', updatedWarehouse)

  Innoclapps.success(t('warehouse::warehouse.updated'))

  handleHidden()
}

async function prepareComponent() {
  const [_warehouse, _fields] = await Promise.all([
    retrieveResource(currentWarehouseId.value),
    getUpdateFields(resourceName, currentWarehouseId.value),
  ])

  warehouse.value = {
    ..._warehouse,
    authorizations: {
      update: true,
      ...(_warehouse.authorizations || {}),
    },
  }

  fields.value = _fields
  hydrateFields(_warehouse)
}

watch(currentWarehouseId, prepareComponent, { immediate: true })
</script>
VUE;

write_file($floatingRelative, $floatingModal);

// -----------------------------------------------------------------------------
// 2) Register WarehouseFloatingModal globally in module app.js
// -----------------------------------------------------------------------------
$appPath = file_path($appRelative);
if (! file_exists($appPath)) {
    write_file($appRelative, "import WarehouseFloatingModal from './components/WarehouseFloatingModal.vue'\n\nInnoclapps.booting(app => {\n  app.component('WarehouseFloatingModal', WarehouseFloatingModal)\n})\n");
} else {
    $app = file_get_contents($appPath);

    if (! str_contains($app, "WarehouseFloatingModal")) {
        // Place the import after the existing import block when possible.
        if (preg_match('/^(?:import[^\n]+\n)+/m', $app, $m)) {
            $app = substr_replace(
                $app,
                $m[0] . "import WarehouseFloatingModal from './components/WarehouseFloatingModal.vue'\n",
                0,
                strlen($m[0])
            );
        } else {
            $app = "import WarehouseFloatingModal from './components/WarehouseFloatingModal.vue'\n" . $app;
        }

        $app .= "\n\nInnoclapps.booting(app => {\n  app.component('WarehouseFloatingModal', WarehouseFloatingModal)\n})\n";
    } elseif (! preg_match("/app\.component\(['\"]WarehouseFloatingModal['\"]\s*,\s*WarehouseFloatingModal\)/", $app)) {
        $app .= "\n\nInnoclapps.booting(app => {\n  app.component('WarehouseFloatingModal', WarehouseFloatingModal)\n})\n";
    }

    write_file($appRelative, $app);
}

// -----------------------------------------------------------------------------
// 3) Align WarehousesView.vue with Core floating resource modal contract
// -----------------------------------------------------------------------------
$view = read_file($viewRelative);

// Remove direct edit component import and old inline modal state imports.
$view = str_replace("import WarehousesEdit from './WarehousesEdit.vue'\n", '', $view);
$view = preg_replace(
    "/import \{\s*computed\s*,\s*provide\s*,\s*watch\s*,\s*ref\s*,\s*nextTick\s*\} from 'vue'/",
    "import { computed, provide, watch } from 'vue'",
    $view
);
$view = preg_replace(
    "/import \{\s*computed\s*,\s*provide\s*,\s*watch\s*,\s*ref\s*\} from 'vue'/",
    "import { computed, provide, watch } from 'vue'",
    $view
);

if (! str_contains($view, "@/Core/composables/useFloatingResourceModal")) {
    $view = str_replace(
        "import { useResourceFields } from '@/Core/composables/useResourceFields'\n",
        "import { useResourceFields } from '@/Core/composables/useResourceFields'\nimport { useFloatingResourceModal } from '@/Core/composables/useFloatingResourceModal'\n",
        $view
    );
}

// Replace the whole navbar actions slot with stable first-party style buttons.
$actionsSlot = <<<'VUE'
<template #actions>
      <NavbarSeparator class="hidden lg:block" />

      <NavbarItems>
        <IButton
          basic
          icon="ChevronLeft"
          :text="backButtonText"
          @click="goBackToIndex"
        />

        <IButton
          v-if="safeResource.authorizations.update"
          variant="primary"
          icon="PencilSquareSolid"
          :text="editButtonText"
          @click="openEditFloatingModal"
        />
      </NavbarItems>
    </template>
VUE;

$view = replace_or_fail('/<template #actions>[\s\S]*?<\/template>/', $actionsSlot, $view, 'Could not replace #actions slot in WarehousesView.vue');

// Remove legacy inline/teleport edit rendering.
$view = preg_replace('/\n\s*<Teleport to="body">[\s\S]*?<\/Teleport>\s*/', "\n", $view);
$view = preg_replace('/\n\s*<WarehousesEdit[\s\S]*?\/>
/', "\n", $view);

// Remove hard-route/window.location remnants if any.
$view = str_replace('goToEditPage', 'openEditFloatingModal', $view);
$view = preg_replace('/function goToEditPage\(\)\s*\{[\s\S]*?\}\n/', '', $view);

// Ensure back/edit text helpers exist. Replace previous versions if present.
$helperBlock = <<<'JS'
function translateText(key, fallback) {
  const translated = Innoclapps.t ? Innoclapps.t(key) : null

  return typeof translated === 'string' ? translated : fallback
}

const backButtonText = computed(() =>
  translateText('warehouse::warehouse.back_to_warehouses', 'Back to warehouses')
)

const editButtonText = computed(() => translateText('core::app.edit', 'Edit'))

function goBackToIndex() {
  router.push({ name: 'warehouse-index' })
}

const { floatResourceInEditMode } = useFloatingResourceModal()

function openEditFloatingModal() {
  floatResourceInEditMode({
    resourceName,
    resourceId: Number(warehouseId.value) || warehouseId.value,
  })
}
JS;

// Remove old text/helper/inline edit block from const backButtonText through handleInlineEditUpdated or through showInlineEditForm block.
$view = preg_replace('/\nconst backButtonText = computed\(\(\) => \{[\s\S]*?async function handleInlineEditUpdated\([\s\S]*?\n\}\n/', "\n" . $helperBlock . "\n", $view, 1, $replacedHelper);

if ($replacedHelper === 0) {
    $view = preg_replace('/\nconst showInlineEditForm = ref\(false\)[\s\S]*?async function handleInlineEditUpdated\([\s\S]*?\n\}\n/', "\n" . $helperBlock . "\n", $view, 1, $replacedInline);

    if ($replacedInline === 0) {
        // Insert after resourcePath as a fallback.
        $view = str_replace(
            "const resourcePath = computed(() => `/${resourceName}/${warehouseId.value}`)\n",
            "const resourcePath = computed(() => `/${resourceName}/${warehouseId.value}`)\n\n" . $helperBlock . "\n",
            $view
        );
    }
}

// Clean duplicate blank lines a bit.
$view = preg_replace("/\n{4,}/", "\n\n\n", $view);

write_file($viewRelative, $view);

// -----------------------------------------------------------------------------
// 4) Add first-party edit floating action to Warehouse Resource
// -----------------------------------------------------------------------------
$resource = read_file($resourceRelative);

if (! str_contains($resource, 'use Modules\Core\Actions\Action;')) {
    $resource = str_replace(
        "use Illuminate\Database\Eloquent\Builder;\n",
        "use Illuminate\Database\Eloquent\Builder;\nuse Modules\Core\Actions\Action;\n",
        $resource
    );
}

if (! str_contains($resource, 'floatResourceInEditMode')) {
    $needle = "return [\n";
    $replacement = "return [\n            Action::make()->floatResourceInEditMode(),\n\n";
    $pos = strpos($resource, $needle, strpos($resource, 'public function actions'));
    if ($pos === false) {
        fwrite(STDERR, "Could not locate actions() return array in Warehouse Resource.\n");
        exit(1);
    }
    $resource = substr_replace($resource, $replacement, $pos, strlen($needle));
}

write_file($resourceRelative, $resource);

// -----------------------------------------------------------------------------
// 5) Write docs/checkpoint
// -----------------------------------------------------------------------------
write_file('docs/ai/03-architecture/module-builder-edit-contract.md', <<<'MD'
# Module Builder Edit Contract

First-party ConcordCRM resources use two primary edit patterns:

1. Core CRM resources such as Contacts, Companies and Deals expose a resource action:
   `Action::make()->floatResourceInEditMode()`.
   Their detail pages are normal `/resource/:id` pages and edit is floated through the global
   floating resource modal contract.

2. Activities use index child named views (`components: { edit: ActivitiesEdit }`) because the
   activity list/calendar UX needs a route-backed edit modal.

Warehouse follows pattern #1:

- `Modules\Warehouse\Resources\Warehouse::actions()` includes `Action::make()->floatResourceInEditMode()`.
- `WarehouseFloatingModal.vue` is registered globally as `WarehouseFloatingModal`.
- `WarehousesView.vue` calls `useFloatingResourceModal().floatResourceInEditMode(...)`.
- The detail page does not directly mount `WarehousesEdit.vue` and does not use hard `window.location` routing.

Builder implication:

- Feature toggle: `floating_edit_modal`.
- Required frontend component: `<SingularResource>FloatingModal.vue`.
- Required PHP resource action: `Action::make()->floatResourceInEditMode()`.
- Required app boot registration: `app.component('<SingularResource>FloatingModal', <SingularResource>FloatingModal)`.
MD);

write_file('docs/ai/04-docops/history/2026-06-28-warehouse-edit-first-party-floating-modal-fix.md', <<<'MD'
# 2026-06-28 — Warehouse Edit First-Party Floating Modal Fix

## Problem

Previous detail edit attempts used direct inline component mounting or hard route navigation. That diverged from first-party CRM resources and created UI instability.

## Discovery

- Contacts, Companies and Deals use `Action::make()->floatResourceInEditMode()` in PHP resource actions.
- Contacts/Companies/Deals do not use separate `/:id/edit` detail routes for normal CRM edit behavior.
- Activities are the exception and use named child router views for edit.

## Fix

- Added `WarehouseFloatingModal.vue`.
- Registered it globally in `modules/Warehouse/resources/js/app.js`.
- Updated `WarehousesView.vue` to use `useFloatingResourceModal().floatResourceInEditMode(...)`.
- Added `Action::make()->floatResourceInEditMode()` to `Warehouse` resource actions.
- Removed legacy direct inline edit modal mounting from `WarehousesView.vue`.

## Builder Learning

For generated CRM-style resources, prefer the first-party floating modal contract instead of custom hard routes.
MD);

fwrite(STDOUT, "Warehouse edit first-party floating modal fix applied.\n");
