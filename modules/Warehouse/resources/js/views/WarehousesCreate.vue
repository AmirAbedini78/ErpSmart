<template>
  <ISlideover
    id="createWarehouseModal"
    :title="$t('warehouse::warehouse.create')"
    visible
    static
    form
    @submit="create"
    @hidden="$router.back"
  >
    <FieldsPlaceholder v-if="!hasFields" />

    <FormFields
      :fields="fields"
      :form="form"
      :resource-name="resourceName"
      is-floating
      focus-first
      @update-field-value="form.fill($event.attribute, $event.value)"
      @set-initial-value="form.set($event.attribute, $event.value)"
    />

    <template #modal-ok>
      <IExtendedDropdown
        type="submit"
        :disabled="form.busy"
        :loading="form.busy"
        :text="$t('core::app.create')"
      >
        <IDropdownMenu class="min-w-48">
          <IDropdownItem
            :text="$t('core::app.create_and_add_another')"
            @click="createAndAddAnother"
          />

          <IDropdownItem
            :text="$t('core::app.create_and_go_to_list')"
            @click="createAndGoToList"
          />
        </IDropdownMenu>
      </IExtendedDropdown>
    </template>
  </ISlideover>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

import { useForm } from '@/Core/composables/useForm'
import { useResourceable } from '@/Core/composables/useResourceable'
import { useResourceFields } from '@/Core/composables/useResourceFields'

const emit = defineEmits(['created'])

const resourceName = Innoclapps.resourceName('warehouses')

const { t } = useI18n()
const router = useRouter()

const { fields, hasFields, getCreateFields } = useResourceFields()
const { form } = useForm()
const { createResource } = useResourceable(resourceName)

function create() {
  makeCreateRequest().then(() => router.back())
}

function createAndAddAnother() {
  makeCreateRequest().then(() => form.reset())
}

function createAndGoToList() {
  makeCreateRequest().then(() => router.push('/warehouses'))
}

async function makeCreateRequest() {
  try {
    let warehouse = await createResource(form)

    emit('created', warehouse)

    Innoclapps.success(t('warehouse::warehouse.created'))

    return warehouse
  } catch (e) {
    if (e.isValidationError()) {
      Innoclapps.error(t('core::app.form_validation_failed'), 3000)
    }

    return Promise.reject(e)
  }
}

async function prepareComponent() {
  fields.value = await getCreateFields(resourceName)
}

prepareComponent()
</script>

