<template>
  <Create
    :title="modalTitle"
    :ok-text="
      hasSelectedExistingCompany
        ? $t('core::app.associate')
        : $t('core::app.create')
    "
    :[viaResource]="viaResource ? [parentResource] : undefined"
    :fields-visible="!hasSelectedExistingCompany"
    :with-extended-submit-buttons="!hasSelectedExistingCompany"
  >
    <template #top="{ isReady }">
      <div
        v-if="viaResource"
        v-show="isReady"
        class="mb-4 rounded-lg border border-neutral-300 bg-neutral-50/80 px-4 py-3 dark:border-neutral-500/30 dark:bg-neutral-500/10"
      >
        <FormFields
          :fields="associateField"
          :form="associateForm"
          :resource-name="resourceName"
          is-floating
          @update-field-value="
            associateForm.fill($event.attribute, $event.value)
          "
          @set-initial-value="associateForm.set($event.attribute, $event.value)"
        />
      </div>
    </template>

    <slot name="top" :is-ready="hasFields" />

    <template #after-contact_id-field>
      <ILink class="-mt-1 block text-right" @click="contactBeingCreated = true">
        &plus; {{ $t('contacts::contact.create') }}
      </ILink>
    </template>
  </Create>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

import { useForm } from '@/Core/composables/useForm'
import { usePageTitle } from '@/Core/composables/usePageTitle'

import Create from '../../components/pages/Create.vue'

const props = defineProps({
  viaResource: String,
  parentResource: Object,
})

const resourceName = Innoclapps.resourceName('pages')

const { t } = useI18n()
const { form: associateForm } = useForm()

const hasSelectedExistingCompany = computed(() => !!associateForm.companies)

const modalTitle = computed(() => {
  if (!props.viaResource) {
    return t('Create page')
  }

  if (!hasSelectedExistingCompany.value) {
    return t('saas::saas.fields.page.base_price', {
      name: props.parentResource.display_name,
    })
  }

  return t('saas::saas.associate_with.page', {
    name: props.parentResource.display_name,
  })
})

if (!props.viaResource) {
  usePageTitle(t('saas::saas.create.page'))
}
</script>
