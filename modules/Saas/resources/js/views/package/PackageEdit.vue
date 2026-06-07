<template>
  <ISlideover
    id="editProductModal"
    :ok-loading="form.busy"
    :ok-text="$t('core::app.save')"
    :title="$t('saas::saas.fields.package.edit')"
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
  </ISlideover>
</template>

<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'

import { useForm } from '@/Core/composables/useForm'
import { useResourceable } from '@/Core/composables/useResourceable'
import { useResourceFields } from '@/Core/composables/useResourceFields'

import { usePackages } from '../../composables/usePackages'

const emit = defineEmits(['updated'])

const resourceName = Innoclapps.resourceName('packages')

const { t } = useI18n()
const router = useRouter()
const route = useRoute()
const { fetchPackage } = usePackages()

const { fields, hasFields, getUpdateFields, hydrateFields } =
  useResourceFields()

const { form } = useForm()
const { updateResource } = useResourceable(resourceName)

const pkg = ref(null)

function testConnection() {
  form.db_test = true
  update()
}

async function update() {
  try {
    let res = await updateResource(form, route.params.id)

    if (res?.message) {
      Innoclapps.success(res.message)
    } else {
      Innoclapps.success(t('saas::saas.fields.package.updated'))
    }

    emit('updated', res)
  } catch (e) {
    if (e.isValidationError()) {
      Innoclapps.error(t('core::app.form_validation_failed'), 3000)
    }

    Innoclapps.error(e?.response?.data?.message, 3000)

    return Promise.reject(e)
  }

  if (form.db_test) {
    form.db_test = false
  }

  await router.push({ name: 'tenant-index' })
}

async function prepareComponent() {
  const [_package, _fields] = await Promise.all([
    fetchPackage(route.params.id),
    getUpdateFields(resourceName, route.params.id),
  ])

  fields.value = _fields
  hydrateFields(_package)
  pkg.value = _package
}

prepareComponent()
</script>
