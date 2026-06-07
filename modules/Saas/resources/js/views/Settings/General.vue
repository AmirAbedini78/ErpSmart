<template>
  <IAlert
    variant="info"
    :show="componentReady && !originalSettings?.invoice_module_active"
  >
    <IAlertBody>
      {{ t('saas::saas.invoice_module_requirement_info') }}
      <a
        class="mr-1.5 text-base/6 text-primary-600 no-underline last:mr-0 hover:text-primary-900 focus:outline-none dark:text-primary-300 dark:hover:text-primary-400 sm:text-sm/6"
        href="https://1.envato.market/themesic"
        target="_blank"
      >
        https://1.envato.market/themesic
      </a>
    </IAlertBody>
  </IAlert>

  <div class="my-5 items-center justify-between">
    <div v-if="!originalSettings.saas_module_active && componentReady">
      <ICard
        as="form"
        :overlay="!componentReady"
        @submit.prevent="submitActivationForm"
      >
        <ICardHeader>
          <ICardHeading :text="$t('saas::saas.title')" />
        </ICardHeader>

        <ICardBody>
          <div class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-6">
            <div class="sm:col-span-12">
              <IFormGroup
                label-for="activation_code"
                :label="$t('saas::saas.setting.activation_code')"
                required
              >
                <IFormInput
                  id="activation_code"
                  v-model="form.saas_activation_code"
                />
              </IFormGroup>
            </div>
          </div>
        </ICardBody>

        <ICardFooter class="text-right">
          <IButton
            type="submit"
            variant="primary"
            :disabled="submittingActivationForm"
            :text="$t('saas::saas.setting.activate')"
          />
        </ICardFooter>
      </ICard>
    </div>

    <ICard
      v-if="originalSettings.saas_module_active"
      as="form"
      :overlay="!componentReady"
      @submit.prevent="submit"
    >
      <ICardBody>
        <div class="grid grid-cols-1">
          <IFormGroup label-for="domain" label="Set Domain">
            <IFormInput
              id="domain"
              v-model="form.domain"
              placeholder="concord.com"
            />
          </IFormGroup>

          <IFormGroup
            label-for="create_invoice_before"
            :label="$t('saas::saas.setting.create_invoice_before')"
          >
            <IFormInput
              id="create_invoice_before"
              v-model="form.before_expiry_notification"
              type="number"
              :disabled="!originalSettings?.invoice_module_active"
            />
          </IFormGroup>

          <IFormGroup
            label-for="overdue_days"
            :label="$t('saas::saas.setting.overdue_days')"
          >
            <IFormInput
              id="overdue_days"
              v-model="form.overdue_days"
              type="number"
            />
          </IFormGroup>

          <IFormGroup
            label-for="enable_url_landing_page"
            :label="$t('saas::saas.setting.landing_page_url')"
          >
            <IFormRadioField>
              <IFormRadio
                v-model="form.enable_landing_page_url"
                :value="true"
              />

              <IFormRadioLabel :text="$t('core::app.yes')" />
            </IFormRadioField>

            <IFormRadioField>
              <IFormRadio
                v-model="form.enable_landing_page_url"
                :value="false"
              />

              <IFormRadioLabel :text="$t('core::app.no')" />
            </IFormRadioField>
          </IFormGroup>

          <IFormGroup
            v-if="form.enable_landing_page_url"
            label-for="landing_page_url"
            label="Set Landing Page URL"
          >
            <IFormInput
              id="landing_page_url"
              v-model="form.landing_page_url"
              placeholder="https://mycrm.com/home"
            />
          </IFormGroup>

          <IFormGroup
            v-if="form.enable_landing_page_url"
            label-for="mode"
            label="Mode"
          >
            <IFormSelect id="mode" v-model="form.mode">
              <option value="proxy">Proxy</option>

              <option value="redirection">Redirection</option>
            </IFormSelect>
          </IFormGroup>

          <IFormGroup
            v-if="!form.enable_landing_page_url"
            label-for="landing_page"
            label="Select Landing Page"
          >
            <IFormSelect id="landing_page" v-model="form.landing_page">
              <option
                v-for="page in landingPages"
                :key="page.id"
                :value="page.id"
              >
                {{ page.name }}
              </option>
            </IFormSelect>
          </IFormGroup>

          <IFormSwitchField>
            <IFormSwitchLabel
              :text="$t('saas::saas.setting.enable_subdomain_signup')"
            />

            <IFormSwitch v-model="form.enable_subdomain_signup" />
          </IFormSwitchField>

          <IFormSwitchField>
            <IFormSwitchLabel
              :text="$t('saas::saas.setting.enable_custom_domain_signup')"
            />

            <IFormSwitch v-model="form.enable_custom_domain_signup" />
          </IFormSwitchField>

          <IFormSwitchField>
            <IFormSwitchLabel
              :text="$t('saas::saas.setting.tenant_registration')"
            />

            <IFormSwitch v-model="form.tenant_registration" />
          </IFormSwitchField>
        </div>
      </ICardBody>

      <ICardFooter class="text-right">
        <IButton
          type="submit"
          variant="primary"
          :disabled="form.busy"
          :text="$t('core::app.save')"
        />
      </ICardFooter>
    </ICard>
  </div>
</template>

<script setup>
import { nextTick, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import axios from 'axios'

import { useSettings } from '@/Core/composables/useSettings'

const { te, t } = useI18n()
const landingPages = ref([])

const {
  form,
  isReady: componentReady,
  submit,
  originalSettings,
} = useSettings()

const submittingActivationForm = ref(false)

// Initialize form fields
form.enable_pricing_mode = ref(false)
form.invoice_payment_requirement_status = ref('overdue')
form.alternative_base_host = ref('')
form.tenant_path = ref('')
form.reserved_tenant_ids = ref('www,app,deal,controller,master,ww3,hack')
form.extra_paths = ref('/admin/settings, /config')
form.landing_page_url = ref('')
form.landing_page = ref('')
form.mode = ref('')
form.enable_landing_page_url = ref(false)
form.enable_subdomain_signup = ref(false)
form.enable_custom_domain_signup = ref(false)
form.create_invoice_before = ref('')
form.overdue_days = ref()
form.tenant_registration = ref()

const fetchLandingPages = async () => {
  try {
    const response = await axios.get('/api/saas/pages')
    await nextTick()
    landingPages.value = response.data || []
  } catch (error) {
    console.error('Error fetching landing pages:', error)
  }
}

onMounted(fetchLandingPages)

if (!te('saas::saas.setting.activation_code')) {
  Innoclapps.request()
    .post('/generate-translations')
    .then(() => window.location.reload())
    .catch(error => console.error(error))
}

async function submitActivationForm() {
  await nextTick()
  submittingActivationForm.value = true

  form
    .post('modules/saas/activation')
    .then(() => {
      Innoclapps.success(t('saas::saas.setting.activation_success'))

      window.location.reload()
    })
    .catch(error => {
      Innoclapps.error(error.response.data.message)
    })
    .finally(() => {
      submittingActivationForm.value = false
    })
}
</script>

<style scoped>
.settings-container {
  max-width: 800px;
  margin: auto;
  padding: 20px;
  background: white;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  border-radius: 8px;
}
</style>
