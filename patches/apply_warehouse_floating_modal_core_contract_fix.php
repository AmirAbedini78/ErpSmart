<?php

declare(strict_types=1);

$root = dirname(__DIR__);

function write_file(string $path, string $content): void
{
    $dir = dirname($path);
    if (! is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    file_put_contents($path, $content);
}

function backup_file(string $path, string $suffix): void
{
    if (is_file($path)) {
        copy($path, $path.'.bak-'.$suffix.'-'.date('YmdHis'));
    }
}

$suffix = 'warehouse-floating-modal-core-contract-fix';

$componentPath = $root.'/modules/Warehouse/resources/js/components/WarehouseFloatingModal.vue';
$viewPath = $root.'/modules/Warehouse/resources/js/views/WarehousesView.vue';

backup_file($componentPath, $suffix);
backup_file($viewPath, $suffix);

$component = <<<'VUE'
<template>
  <ISlideover
    id="warehouseFloatingModal"
    :visible="visible"
    :ok-disabled="okDisabled"
    :ok-loading="form.busy"
    :ok-text="$t('core::app.save')"
    :title="modalTitle"
    form
    @update:visible="$emit('update:visible', $event)"
    @hidden="$emit('hidden')"
    @submit="update"
  >
    <FieldsPlaceholder v-if="!floatingReady || !hasFields" />

    <FormFields
      v-else-if="isEditMode"
      :fields="fields"
      :form="form"
      :resource-name="resourceName"
      :resource-id="resource.id"
      is-floating
      @update-field-value="form.fill($event.attribute, $event.value)"
      @set-initial-value="form.set($event.attribute, $event.value)"
    />

    <div v-else class="space-y-4">
      <ITextDisplay :text="resource.display_name || resource.name" />
      <ITextBlockDark :text="resource.code" />
    </div>
  </ISlideover>
</template>

<script setup>
import { computed } from 'vue'
import { useForm } from '@/Core/composables/useForm'

defineOptions({ name: 'WarehouseFloatingModal' })

const emit = defineEmits([
  'update:visible',
  'hidden',
  'updated',
  'actionExecuted',
  'viewRequested',
])

const props = defineProps({
  visible: { type: Boolean, default: false },
  floatingReady: { type: Boolean, default: false },
  resource: { type: Object, required: true },
  fields: { type: Array, default: () => [] },
  mode: { type: String, default: 'edit' },
  updateHandler: { type: Function, required: true },
})

const resourceName = Innoclapps.resourceName('warehouses')
const { form } = useForm()

const isEditMode = computed(() => props.mode === 'edit')
const hasFields = computed(() => Array.isArray(props.fields) && props.fields.length > 0)

const modalTitle = computed(() => {
  const label = props.resource.display_name || props.resource.name || props.resource.code || ''
  const prefix = translateText('core::app.edit', 'Edit')

  return label ? `${prefix} ${label}` : prefix
})

const okDisabled = computed(() => {
  if (!isEditMode.value) {
    return true
  }

  return (
    form.busy ||
    !props.floatingReady ||
    !hasFields.value ||
    props.resource?.authorizations?.update === false
  )
})

function translateText(key, fallback) {
  const translated = Innoclapps.t ? Innoclapps.t(key) : null

  return typeof translated === 'string' ? translated : fallback
}

async function update() {
  const updatedWarehouse = await props.updateHandler(form)

  emit('updated', updatedWarehouse || props.resource)
  emit('update:visible', false)
}
</script>
VUE;

write_file($componentPath, $component);

if (is_file($viewPath)) {
    $view = file_get_contents($viewPath);
    $view = str_replace('sm:flex-rowsm:items-start', 'sm:flex-row sm:items-start', $view);
    // Ensure the edit action stays on the Core floating modal contract.
    if (strpos($view, "import { useFloatingResourceModal } from '@/Core/composables/useFloatingResourceModal'") === false) {
        $view = str_replace(
            "import ResourceMediaPanel from '@/Core/components/Resource/ResourceMediaPanel.vue'\n",
            "import ResourceMediaPanel from '@/Core/components/Resource/ResourceMediaPanel.vue'\nimport { useFloatingResourceModal } from '@/Core/composables/useFloatingResourceModal'\n",
            $view
        );
    }
    file_put_contents($viewPath, $view);
}

$history = <<<'MD'
# Warehouse Floating Modal Core Contract Fix

The Warehouse edit floating modal must follow the first-party Core contract used by `TheFloatingResourceModal.vue`.

Core does **not** pass `resourceId` to the resource floating modal component. It passes:

- `visible`
- `floating-ready`
- `resource`
- `fields`
- `mode`
- `update-handler`

The Warehouse floating modal was rewritten to consume that contract directly instead of retrieving/updating the record independently.

Builder note: CRM-style module edit should use `floating_edit_modal` with a module-specific `{SingularName}FloatingModal` component that accepts the Core floating modal props.
MD;

write_file($root.'/docs/ai/04-docops/history/2026-06-28-warehouse-floating-modal-core-contract-fix.md', $history);

$architecture = <<<'MD'
# Module Builder Edit Contract

For CRM-style resources such as contacts, companies and deals, the edit action uses Core floating resource modal infrastructure.

## Core contract

`TheFloatingResourceModal.vue` dynamically renders:

```vue
<component
  :is="`${resourceInformation.singularName}FloatingModal`"
  v-model:visible="isVisible"
  :floating-ready="floatingReady"
  :resource="resource"
  :fields="fields"
  :mode="mode"
  :update-handler="updateHandler"
/>
```

Therefore generated module floating modals must accept:

- `visible`
- `floatingReady`
- `resource`
- `fields`
- `mode`
- `updateHandler`

They should not require `resourceId` because Core already owns the resource fetch/update lifecycle.
MD;

write_file($root.'/docs/ai/03-architecture/module-builder-edit-contract.md', $architecture);

echo "Warehouse floating modal Core contract fix applied.\n";
