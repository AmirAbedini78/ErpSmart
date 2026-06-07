<template>
  <ISlideover
    id="createCompanyModal"
    :visible="visible"
    :title="title || $t('saas::saas.quotas.create')"
    :ok-text="$t('core::app.create')"
    :ok-disabled="form.busy"
    static
    form
    @hidden="handleModalHiddenEvent"
    @submit="createUsing ? createUsing(create) : createAndGoToList()"
    @update:visible="$emit('update:visible', $event)"
  >
    <FieldsPlaceholder v-if="!hasFields" />

    <slot name="top" :is-ready="hasFields" />

    <div v-show="fieldsVisible">
      <FormFields
        :fields="fields"
        :form="form"
        :resource-name="resourceName"
        is-floating
        focus-first
        @update-field-value="form.fill($event.attribute, $event.value)"
        @set-initial-value="form.set($event.attribute, $event.value)"
      >
      </FormFields>
    </div>

    <template v-if="withExtendedSubmitButtons" #modal-ok>
      <IExtendedDropdown
        type="submit"
        placement="top-end"
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
            v-show="goToList"
            :text="$t('core::app.create_and_go_to_list')"
            @click="createAndGoToList"
          />
        </IDropdownMenu>
      </IExtendedDropdown>
    </template>

    <CreateDealModal
      v-model:visible="dealBeingCreated"
      :overlay="false"
      @created="dealBeingCreated = false"
    />

    <CreateContactModal
      v-model:visible="contactBeingCreated"
      :overlay="false"
      @created="contactBeingCreated = false"
      @restored="contactBeingCreated = false"
    />
  </ISlideover>
</template>

<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { whenever } from '@vueuse/core'
import findIndex from 'lodash/findIndex'

import { useForm } from '@/Core/composables/useForm'
import { useResourceable } from '@/Core/composables/useResourceable'
import { useResourceFields } from '@/Core/composables/useResourceFields'

const props = defineProps({
  visible: { type: Boolean, default: true },
  goToList: { type: Boolean, default: true },
  redirectToView: Boolean,
  createUsing: Function,
  withExtendedSubmitButtons: Boolean,
  fieldsVisible: { type: Boolean, default: true },
  title: String,

  contacts: Array,
  deals: Array,
})

const emit = defineEmits(['created', 'restored', 'update:visible', 'ready'])

const { t } = useI18n()
const router = useRouter()

const resourceName = Innoclapps.resourceName('quotas')

const dealBeingCreated = ref(false)
const contactBeingCreated = ref(false)
const productBeingCreated = ref(false)

const { fields, hasFields, findField, getCreateFields } = useResourceFields()

const { form } = useForm()
const { createResource } = useResourceable(resourceName)

whenever(() => props.visible, prepareComponent, { immediate: true })

function onAfterCreate(data) {
  data.indexRoute = { name: 'quotas-index' }

  if (data.action === 'go-to-list') {
    return router.push(data.indexRoute)
  }
}

function handleModalHiddenEvent() {
  fields.value = []
  form.reset()
}

function create() {
  makeCreateRequest().then(onAfterCreate)
}

function createAndAddAnother() {
  makeCreateRequest('create-another').then(data => {
    form.reset()
    onAfterCreate(data)
  })
}

function createAndGoToList() {
  makeCreateRequest('go-to-list').then(onAfterCreate)
}

async function makeCreateRequest(actionType = null) {
  let company = await createResource(form).catch(e => {
    if (e.isValidationError()) {
      Innoclapps.error(t('core::app.form_validation_failed'), 3000)
    }

    return Promise.reject(e)
  })

  let payload = {
    company: company,
    isRegularAction: actionType === null,
    action: actionType,
  }

  emit('created', payload)

  Innoclapps.success(t('core::resource.created'))

  return payload
}

async function prepareComponent() {
  const createFields = await getCreateFields(resourceName)
  const findFieldIndex = attr => findIndex(createFields, ['attribute', attr])

  // From props, same attribute name and prop name
  for (let attribute of ['contacts', 'deals']) {
    if (!props[attribute]) continue
    let fIdx = findFieldIndex(attribute)

    // Perhaps is not visible?
    if (fIdx === -1) {
      form.set(
        attribute,
        props[attribute].map(record =>
          typeof record === 'object' ? record.id : record
        )
      )
    } else {
      createFields[fIdx].value = props[attribute]
    }
  }

  fields.value = createFields

  emit('ready', { fields, form })
}
</script>
