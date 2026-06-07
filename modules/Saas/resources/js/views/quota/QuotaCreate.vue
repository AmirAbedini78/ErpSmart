<template>
  <Create
    :title="modalTitle"
    :[viaResource]="viaResource ? [parentResource] : undefined"
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
  </Create>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

import { useForm } from '@/Core/composables/useForm'
import { usePageTitle } from '@/Core/composables/usePageTitle'

import Create from '../../components/quotas/Create.vue'

const props = defineProps({
  viaResource: String,
  parentResource: Object,
})

const emit = defineEmits(['associated'])

const resourceName = Innoclapps.resourceName('quotas')

const { t } = useI18n()

const { form: associateForm } = useForm()

const modalTitle = computed(() => {
  if (!props.viaResource) {
    return t('Create Quota')
  }

  if (!hasSelectedExistingCompany.value) {
    return t('saas::saas.create_with.quota', {
      name: props.parentResource.display_name,
    })
  }

  return t('saas::saas.associate_with.quota', {
    name: props.parentResource.display_name,
  })
})

async function associate() {
  await associateForm
    .set({ companies: [associateForm.companies] }) // set the value as an array
    .put(`associations/${props.viaResource}/${props.parentResource.id}`)

  emit('associated', associateForm.companies[0])

  Innoclapps.success(t('core::resource.associated'))
}

if (!props.viaResource) {
  usePageTitle(t('Create Quota'))
}
</script>
