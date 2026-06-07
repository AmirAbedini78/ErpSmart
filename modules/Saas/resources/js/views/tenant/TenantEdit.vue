<template>
  <ISlideover
    id="editProductModal"
    :ok-loading="form.busy"
    :ok-text="$t('core::app.save')"
    :title="$t('saas::saas.fields.tenant.edit')"
    visible
    static
    form
    @hidden="$router.back"
    @submit="update"
  >
    <FieldsPlaceholder v-if="!hasFields" />

    <FormFields
      :fields="fields"
      :form="form"
      :resource-name="resourceName"
      :resource-id="$route.params.id"
      is-floating
      @update-field-value="form.fill($event.attribute, $event.value)"
      @set-initial-value="form.set($event.attribute, $event.value)"
    >

    </FormFields>
  </ISlideover>
</template>

<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'

import { useForm } from '@/Core/composables/useForm'
import { useResourceable } from '@/Core/composables/useResourceable'
import { useResourceFields } from '@/Core/composables/useResourceFields'

import { useTenants } from '../../composables/useTenants'

const emit = defineEmits(['updated'])

const resourceName = Innoclapps.resourceName('tenants')

const { t } = useI18n()
const router = useRouter()
const route = useRoute()
const { fetchTenant } = useTenants()

const { fields, hasFields, getUpdateFields, hydrateFields } =
  useResourceFields()

const { form } = useForm()
const { updateResource } = useResourceable(resourceName)

const tenant = ref(null)

function testConnection() {
  form.db_test = true
  update()
}

async function update() {
  try {
    let tenant = await updateResource(form, route.params.id)

    if (!form.db_test) {
      emit('updated', tenant)

      Innoclapps.success(t('saas::saas.fields.tenant.updated'))
      router.back()
    } else {
      Innoclapps.success(t('saas::saas.database_test_successful'))
    }
  } catch (e) {
    if (e.isValidationError()) {
      Innoclapps.error(t('core::app.form_validation_failed'), 3000)
    }

    Innoclapps.error(e?.response?.data?.message, 3000)

    return Promise.reject(e)
  } finally {
    form.db_test = false
  }
}

async function prepareComponent() {
  const [_tenant, _fields] = await Promise.all([
    fetchTenant(route.params.id),
    getUpdateFields(resourceName, route.params.id),
  ])

  fields.value = _fields
  hydrateFields(_tenant)

  form.db_host = _tenant.db_host
  form.db_port = _tenant.db_port
  form.database = _tenant.database
  form.db_user = _tenant.db_user
  form.db_password = _tenant.db_password

  tenant.value = _tenant

  setTimeout(() => {
    document
      .getElementById('tenants-subdomain-floating')
      .setAttribute('disabled', 'disabled')

    document
      .getElementById('tenants-domain-floating')
      .setAttribute('disabled', 'disabled')

      document.getElementsByName('db_scheme')[0].parentNode.parentNode.parentNode.parentNode.remove()

  }, 100)
}

prepareComponent()
</script>
