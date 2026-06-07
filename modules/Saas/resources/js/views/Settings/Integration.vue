<template>
  <ICardHeader>
    <ICardHeading :text="$t('saas::saas.setting.integration')" />
  </ICardHeader>

  <ICard as="form" :overlay="!componentReady" @submit.prevent="handleSubmit">
    <ICardBody>
      <div
        v-if="!originalSettings.saas_module_active"
        class="bg-yellow-500 dark:bg-gray-800 dark:text-yellow-300 mb-4 rounded-lg p-4 text-sm"
        style="color: oklch(0.476 0.114 61.907)"
        role="alert"
      >
        Please active Saas module first.
      </div>

      <div v-if="originalSettings.saas_module_active">
        <ITabGroup v-model="activeIntegrationTab">
          <ITabList>
            <ITab @activated="changeIntegrationTab(0)">
              {{ $t('saas::saas.setting.cpanel') }}
            </ITab>

            <ITab @activated="changeIntegrationTab(2)">
              {{ $t('saas::saas.setting.mysql_root') }}
            </ITab>
          </ITabList>

          <ITabPanels>
            <!-- cPanel Settings -->
            <ITabPanel>
              <IFormCheckboxField class="mb-2">
                <IFormCheckbox v-model:checked="form.cpanel_enabled" />

                <IFormCheckboxLabel
                  :text="$t('saas::saas.setting.cpanel_enabled')"
                />
              </IFormCheckboxField>

              <IFormGroup
                label-for="cpanel_login_domain"
                :label="$t('saas::saas.setting.cpanel_login_domain')"
              >
                <IFormInput v-model="form.cpanel_login_domain" />
              </IFormGroup>

              <IFormGroup
                label-for="cpanel_port"
                :label="$t('saas::saas.setting.cpanel_port')"
              >
                <IFormInput v-model="form.cpanel_port" type="number" />
              </IFormGroup>

              <IFormGroup
                label-for="cpanel_username"
                :label="$t('saas::saas.setting.cpanel_username')"
              >
                <IFormInput v-model="form.cpanel_username" />
              </IFormGroup>

              <IFormGroup
                label-for="cpanel_password"
                :label="$t('saas::saas.setting.cpanel_password')"
              >
                <IFormInput v-model="form.cpanel_password" type="password" />
              </IFormGroup>

              <IFormGroup
                label-for="cpanel_db_prefix"
                :label="$t('saas::saas.setting.cpanel_db_prefix')"
              >
                <IFormInput v-model="form.cpanel_db_prefix" />
              </IFormGroup>

              <IFormCheckboxField>
                <IFormCheckbox v-model:checked="form.cpanel_addon_domain" />

                <IFormCheckboxLabel
                  :text="$t('saas::saas.setting.cpanel_addon_domain')"
                />
              </IFormCheckboxField>
            </ITabPanel>

            <!-- MySQL Root Settings -->
            <ITabPanel>
              <IFormCheckboxField class="mb-3">
                <IFormCheckbox v-model:checked="form.mysql_root_enabled" />

                <IFormCheckboxLabel
                  :text="$t('saas::saas.setting.mysql_enabled')"
                />
              </IFormCheckboxField>

              <IFormGroup
                label-for="mysql_host"
                :label="$t('saas::saas.setting.mysql_host')"
              >
                <IFormInput v-model="form.mysql_root_host" />
              </IFormGroup>

              <IFormGroup
                label-for="mysql_port"
                :label="$t('saas::saas.setting.mysql_port')"
              >
                <IFormInput v-model="form.mysql_root_port" type="number" />
              </IFormGroup>

              <IFormGroup
                label-for="mysql_username"
                :label="$t('saas::saas.setting.mysql_username')"
              >
                <IFormInput v-model="form.mysql_root_username" />
              </IFormGroup>

              <IFormGroup
                label-for="mysql_password"
                :label="$t('saas::saas.setting.mysql_password')"
              >
                <IFormInput
                  v-model="form.mysql_root_password"
                  type="password"
                />
              </IFormGroup>

              <IFormCheckboxField>
                <IFormCheckbox v-model:checked="form.mysql_root_create_user" />

                <IFormCheckboxLabel
                  :text="$t('saas::saas.setting.mysql_create_user')"
                />
              </IFormCheckboxField>
            </ITabPanel>
          </ITabPanels>
        </ITabGroup>
      </div>
    </ICardBody>

    <ICardFooter v-if="originalSettings.saas_module_active" class="text-right">
      <IButton
        type="submit"
        variant="primary"
        :disabled="form.busy"
        :text="$t('core::app.save')"
      />
    </ICardFooter>
  </ICard>
</template>

<script setup>
import { ref } from 'vue'
import axios from 'axios'

import { useSettings } from '@/Core/composables/useSettings'

const {
  form,
  isReady: componentReady,
  submit,
  originalSettings,
} = useSettings()

const activeIntegrationTab = ref(0)
const loading = ref(false)
const errorMessage = ref(null)

const changeIntegrationTab = index => {
  activeIntegrationTab.value = index
}

const testDatabaseConnection = async () => {
  try {
    loading.value = true
    errorMessage.value = null

    const response = await axios.post('/saas/test-database-connection', {
      db_host: form.mysql_host,
      database: form.mysql_database,
      db_user: form.mysql_username,
      db_password: form.mysql_password,
    })

    if (response.data.success) {
      return true
    } else {
      errorMessage.value = response.data.message

      return false
    }
  } catch (error) {
    errorMessage.value =
      error.response?.data?.message || 'Database connection failed.'

    return false
  } finally {
    loading.value = false
  }
}

const handleSubmit = async () => {
  if (form.mysql_enabled) {
    const connectionValid = await testDatabaseConnection()
    alert(connectionValid)

    if (!connectionValid) {
      return
    }
  }

  submit()
}
</script>
