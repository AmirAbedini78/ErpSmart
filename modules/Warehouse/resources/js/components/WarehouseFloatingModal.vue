<template>
  <ISlideover
    id="warehouseFloatingModal"
    :visible="visible"
    :ok-disabled="okDisabled"
    :ok-loading="form.busy"
    :ok-text="$t('core::app.save')"
    :title="modalTitle"
    form
    @update:visible="emit('update:visible', $event)"
    @hidden="emit('hidden')"
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