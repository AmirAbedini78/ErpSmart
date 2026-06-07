<template>
  <form class="space-y-6" @submit.prevent="submit">
    <div class="grid grid-cols-1 gap-x-4 sm:grid-cols-6">
      <div class="sm:col-span-3">
        <IFormGroup
          label-for="first_name"
          :label="$t('contacts::fields.contacts.first_name')"
          required
        >
          <IFormInput
            id="first_name"
            v-model="form.first_name"
            type="first_name"
            name="first_name"
            autocomplete="first_name"
            :disabled="form.busy"
            autofocus
          />

          <IFormError :error="form.getError('first_name')" />
        </IFormGroup>
      </div>

      <div class="sm:col-span-3">
        <IFormGroup
          label-for="last_name"
          :label="$t('contacts::fields.contacts.last_name')"
          required
        >
          <IFormInput
            id="last_name"
            ref="last_nameRef"
            v-model="form.last_name"
            name="last_name"
            :disabled="form.busy"
            required
          />

          <IFormError :error="form.getError('last_name')" />
        </IFormGroup>
      </div>

      <div class="sm:col-span-3">
        <IFormGroup
          label-for="email"
          :label="$t('contacts::fields.contacts.email')"
          required
        >
          <IFormInput
            id="email"
            v-model="form.email"
            type="email"
            name="email"
            autocomplete="email"
            :disabled="form.busy"
            required
          />

          <IFormError :error="form.getError('email')" />
        </IFormGroup>
      </div>

      <div class="sm:col-span-3">
        <IFormGroup
          label-for="company_name"
          :label="$t('core::app.company.name')"
          required
        >
          <IFormInput
            id="company_name"
            v-model="form.company_name"
            name="company_name"
            required
          />

          <IFormError :error="form.getError('company_name')" />
        </IFormGroup>
      </div>

      <div class="sm:col-span-3">
        <IFormGroup
          label-for="package"
          :label="$t('saas::saas.package')"
          required
        >
          <ICustomSelect
            v-model="form.package"
            label="name"
            input-id="package"
            :options="props.packages"
            @cleared="form.package = null"
          />

          <IFormError :error="form.getError('package')" />
        </IFormGroup>
      </div>

      <div v-if="props.domainEnabled" class="sm:col-span-3">
        <IFormGroup>
          <template #label>
            <div class="flex items-center">
              <IFormLabel
                class="mb-1"
                for="domain"
                :label="$t('saas::saas.fields.tenant.domain')"
              />

              <span
                v-i-tooltip.bottom.light="t('saas.saas.domain_description')"
              >
                <IButton
                  class="pointer-events-none"
                  icon="QuestionMarkCircle"
                  basic
                  small
                />
              </span>
            </div>
          </template>

          <IFormInput
            id="domain"
            v-model="form.domain"
            name="domain"
            required
          />

          <IFormError :error="form.getError('domain')" />
        </IFormGroup>
      </div>

      <div v-if="props.subdomainEnabled" class="sm:col-span-3">
        <IFormGroup>
          <template #label>
            <div class="flex items-center">
              <IFormLabel
                class="mb-1"
                for="subdomain"
                :label="$t('saas::saas.fields.tenant.subdomain')"
              />

              <span
                v-i-tooltip.bottom.light="t('saas::saas.subdomain_description')"
              >
                <IButton
                  class="pointer-events-none"
                  icon="QuestionMarkCircle"
                  basic
                  small
                />
              </span>
            </div>
          </template>

          <IFormInput
            id="subdomain"
            v-model="form.subdomain"
            name="subdomain"
            required
          />

          <IFormError :error="form.getError('subdomain')" />
        </IFormGroup>
      </div>

      <div class="sm:col-span-3">
        <IFormGroup
          label-for="street"
          :label="$t('contacts::fields.contacts.street')"
        >
          <IFormInput
            id="street"
            v-model="form.street"
            name="street"
            required
          />

          <IFormError :error="form.getError('street')" />
        </IFormGroup>
      </div>

      <div class="sm:col-span-3">
        <IFormGroup
          label-for="city"
          :label="$t('contacts::fields.contacts.city')"
        >
          <IFormInput id="city" v-model="form.city" name="city" required />

          <IFormError :error="form.getError('city')" />
        </IFormGroup>
      </div>

      <div class="sm:col-span-3">
        <IFormGroup
          label-for="state"
          :label="$t('contacts::fields.contacts.state')"
        >
          <IFormInput id="state" v-model="form.state" name="state" required />

          <IFormError :error="form.getError('state')" />
        </IFormGroup>
      </div>

      <div class="sm:col-span-3">
        <IFormGroup label-for="postal_code" :label="$t('saas::saas.zip_code')">
          <IFormInput
            id="postal_code"
            v-model="form.postal_code"
            name="postal_code"
          />

          <IFormError :error="form.getError('postal_code')" />
        </IFormGroup>
      </div>

      <div class="sm:col-span-3">
        <IFormGroup
          label-for="country"
          :label="$t('contacts::fields.contacts.country.name')"
        >
          <ICustomSelect
            v-model="form.country"
            label="name"
            input-id="company_country_id"
            :options="props.countries"
            @cleared="form.company_country_id = null"
          />

          <IFormError :error="form.getError('country')" />
        </IFormGroup>
      </div>
    </div>

    <IFormGroup v-if="reCaptcha.validate">
      <VueRecaptcha
        ref="reCaptchaRef"
        :sitekey="reCaptcha.siteKey"
        @verify="handleReCaptchaVerified"
      />

      <IFormError :error="form.getError('g-recaptcha-response')" />
    </IFormGroup>

    <div class="flex items-center justify-between">
      <ILinkBase
        v-if="!scriptConfig('disable_password_forgot')"
        variant="primary"
        href="/login"
      >
        {{ $t('saas::saas.already_have_account') }}
      </ILinkBase>
    </div>

    <IAlert v-if="error" variant="danger" class="rounded-none">
      <IAlertBody>
        <p>
          {{ errorMessage }}
        </p>
      </IAlertBody>
    </IAlert>

    <IAlert v-if="success" variant="success" class="rounded-none">
      <IAlertBody>
        <p>
          {{ $t('saas::saas.tenant_registration_success') }}
        </p>
      </IAlertBody>
    </IAlert>

    <IButton
      type="submit"
      variant="primary"
      :disabled="submitButtonIsDisabled"
      :loading="requestInProgress"
      :text="$t('saas::saas.register')"
      block
      @click="register"
    />
  </form>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { VueRecaptcha } from 'vue-recaptcha'

import IFormLabel from '@/Core/components/UI/Form/IFormLabel.vue'
import { useApp } from '@/Core/composables/useApp'
import { useForm } from '@/Core/composables/useForm'

const props = defineProps({
  domainEnabled: Boolean,
  subdomainEnabled: Boolean,
  countries: Array,
  packages: Array,
})

const { t } = useI18n()

const { appUrl, scriptConfig } = useApp()

const reCaptcha = scriptConfig('reCaptcha') || {}
const reCaptchaRef = ref(null)
const requestInProgress = ref(false)
const error = ref(false)
const errorMessage = ref('')
const success = ref(false)

const { form } = useForm({
  email: null,
  password: null,
  remember: null,
  'g-recaptcha-response': null,
})

const submitButtonIsDisabled = computed(() => requestInProgress.value)

async function register() {
  requestInProgress.value = true

  await Innoclapps.request(appUrl + '/sanctum/csrf-cookie')

  form
    .post(appUrl + '/tenant/register')
    .then(() => {
      requestInProgress.value = false
      success.value = true
      setTimeout(() => (success.value = false), 3000)
      form.reset()
    })
    .finally(() => {
      requestInProgress.value = false
      reCaptchaRef.value && reCaptchaRef.value.reset()
    })
    .catch(e => {
      if (e.isValidationError()) {
        error.value = true
        errorMessage.value = t('core::app.form_validation_failed')

        setTimeout(() => {
          error.value = false
          errorMessage.value = ''
        }, 5000)
      } else {
        error.value = true
        errorMessage.value = 'An Error occurred'

        setTimeout(() => {
          error.value = false
          errorMessage.value = ''
        }, 3000)
      }
    })
}

function handleReCaptchaVerified(response) {
  form.fill('g-recaptcha-response', response)
}
</script>
