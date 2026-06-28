<template>
  <ISlideover
    id="editWarehouseModal"
    :ok-disabled="form.busy || (hasFields && !warehouse.authorizations.update)"
    :ok-loading="form.busy"
    :ok-text="$t('core::app.save')"
    :title="$t('warehouse::warehouse.edit')"
    visible
    form
    @hidden="$router.back"
    @submit="update"
  >
    <FieldsPlaceholder v-if="!hasFields" />

    <FormFields
      v-else
      :fields="fields"
      :form="form"
      :resource-name="resourceName"
      :resource-id="$route.params.id"
      is-floating
      @update-field-value="form.fill($event.attribute, $event.value)"
      @set-initial-value="form.set($event.attribute, $event.value)"
    />
  </ISlideover>
</template>

<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'

import { useForm } from '@/Core/composables/useForm'
import { useResourceable } from '@/Core/composables/useResourceable'
import { useResourceFields } from '@/Core/composables/useResourceFields'

const emit = defineEmits(['updated'])

const resourceName = Innoclapps.resourceName('warehouses')

const { t } = useI18n()
const router = useRouter()
const route = useRoute()

const { fields, hasFields, getUpdateFields, hydrateFields } =
  useResourceFields()
const { form } = useForm()
const { retrieveResource, updateResource } = useResourceable(resourceName)

const warehouse = ref({})

async function update() {
  let updatedWarehouse = await updateResource(form, route.params.id)

  emit('updated', updatedWarehouse)

  Innoclapps.success(t('warehouse::warehouse.updated'))
  router.back()
}

async function prepareComponent() {
  const [_warehouse, _fields] = await Promise.all([
    retrieveResource(route.params.id),
    getUpdateFields(resourceName, route.params.id),
  ])

  fields.value = _fields
  hydrateFields(_warehouse)
  warehouse.value = _warehouse
}

prepareComponent()
</script>
