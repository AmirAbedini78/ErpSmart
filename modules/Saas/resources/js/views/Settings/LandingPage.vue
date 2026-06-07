<template>
  <div class="mb-5 items-center justify-between">
    <ICard as="form" :overlay="!componentReady" @submit.prevent="submit">
      <ICardBody>
        <div class="grid grid-cols-1">
          <IFormGroup label-for="landing_page" label="Select Landing Page">
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

          <IFormGroup label-for="landing_page_url" label="Landing Page URL">
            <IFormInput
              id="landing_page_url"
              v-model="form.landing_page_url"
              placeholder="https://mycrm.com/home"
            />
          </IFormGroup>
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
import { ref } from 'vue'

import { useSettings } from '@/Core/composables/useSettings'

const {
  form,
  isReady: componentReady,
  submit,
  originalSettings,
} = useSettings()

// Initialize form fields
form.landing_page = ref('')
form.landing_url = ref('')
const landingPages = ref([])

onMounted(async () => {
  try {
    const response = await axios.get('/api/saas/pages')
    landingPages.value = response.data || []
  } catch (error) {
    console.error('Error fetching landing pages:', error)
  }
})
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
