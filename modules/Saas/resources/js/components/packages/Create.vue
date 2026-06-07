<template>
  <ISlideover
    id="createCompanyModal"
    :visible="visible"
    :title="title || $t('saas::saas.packages.create')"
    :ok-text="$t('core::app.create')"
    :ok-disabled="form.busy"
    static
    form
    @hidden="handleModalHiddenEvent"
    @submit="createUsing ? createUsing(create) : createPackage()"
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
        <template v-if="form.db_scheme === 'custom'" #after-db_scheme-field>
          <div class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-4">
            <div class="sm:col-span-2">
              <IFormGroup
                key="db_host"
                label-for="db_host"
                :label="$t('saas::saas.fields.tenant.db_host')"
                required
              >
                <IFormInput
                  id="db_host"
                  v-model="form.db_host"
                  :placeholder="$t('saas::saas.fields.tenant.db_host')"
                />

                <IFormError :error="form.getError('db_host')" />
              </IFormGroup>
            </div>

            <div class="sm:col-span-2">
              <IFormGroup
                key="db_port"
                label-for="db_port"
                :label="$t('saas::saas.fields.tenant.db_port')"
                required
              >
                <IFormInput
                  id="db_port"
                  v-model="form.db_port"
                  :placeholder="$t('saas::saas.fields.tenant.db_port')"
                />

                <IFormError :error="form.getError('db_port')" />
              </IFormGroup>
            </div>
          </div>

          <div class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-4">
            <div class="sm:col-span-2">
              <IFormGroup
                key="db_user"
                label-for="db_user"
                :label="$t('saas::saas.fields.tenant.db_user')"
                required
              >
                <IFormInput
                  id="db_user"
                  v-model="form.db_user"
                  :placeholder="$t('saas::saas.fields.tenant.db_user')"
                />

                <IFormError :error="form.getError('db_user')" />
              </IFormGroup>
            </div>

            <div class="sm:col-span-2">
              <IFormGroup
                key="db_password"
                label-for="db_password"
                :label="$t('saas::saas.fields.tenant.db_password')"
                required
              >
                <IFormInput
                  id="db_password"
                  v-model="form.db_password"
                  :placeholder="$t('saas::saas.fields.tenant.db_password')"
                />

                <IFormError :error="form.getError('db_password')" />
              </IFormGroup>
            </div>
          </div>

          <div class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-4">
            <div class="sm:col-span-2">
              <IFormGroup
                key="database"
                label-for="database"
                :label="$t('saas::saas.fields.tenant.database')"
                required
              >
                <IFormInput
                  id="database"
                  v-model="form.database"
                  :placeholder="$t('saas::saas.fields.tenant.database')"
                />

                <IFormError :error="form.getError('database')" />
              </IFormGroup>
            </div>
          </div>

          <IButton
            variant="primary"
            :loading="form.busy"
            :text="$t('saas::saas.buttons.test_connection')"
            @click="testConnection"
          ></IButton>
        </template>
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

    <ProductsCreate
      v-model:visible="productBeingCreated"
      :overlay="false"
      @created="productBeingCreated = false"
      @restored="productBeingCreated = false"
    />
  </ISlideover>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { whenever } from '@vueuse/core'
import findIndex from 'lodash/findIndex'

import { useForm } from '@/Core/composables/useForm'
import { useResourceable } from '@/Core/composables/useResourceable'
import { useResourceFields } from '@/Core/composables/useResourceFields'

import ProductsCreate from '@/Billable/views/ProductsCreate.vue'

const props = defineProps({
  visible: { type: Boolean, default: true },
  goToList: { type: Boolean, default: true },
  redirectToView: Boolean,
  createUsing: Function,
  withExtendedSubmitButtons: Boolean,
  fieldsVisible: { type: Boolean, default: true },
  title: String,

  products: Array,
})

const emit = defineEmits(['created', 'restored', 'update:visible', 'ready'])
const { t } = useI18n()
const router = useRouter()

const resourceName = Innoclapps.resourceName('packages')

const productBeingCreated = ref(false)

const { fields, hasFields, getCreateFields } = useResourceFields()

const { form } = useForm()
const { createResource } = useResourceable(resourceName)

whenever(() => props.visible, prepareComponent, { immediate: true })

function onAfterCreate(data) {
  data.indexRoute = { name: 'packages-index' }

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

function createPackage() {
  form.db_test = false
  createAndGoToList()
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
  let product = await createResource(form).catch(e => {
    if (e.isValidationError()) {
      Innoclapps.error(t('core::app.form_validation_failed'), 3000)
    }

    return Promise.reject(e)
  })

  let payload = {
    product: product,
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
  for (let attribute of ['products']) {
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

function testConnection() {
  form.db_test = true
  create()
}
</script>

<style>
#gjs {
  border: 1px solid #ddd;
}
</style>
