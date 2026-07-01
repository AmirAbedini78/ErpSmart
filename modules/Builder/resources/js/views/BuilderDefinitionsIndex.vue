<template>
  <MainLayout :overlay="loading">
    <template #actions>
      <NavbarSeparator class="hidden lg:block" />

      <NavbarItems>
        <IButton
          variant="primary"
          icon="PlusSolid"
          text="Create draft"
          :loading="creating"
          @click="createDraft"
        />
      </NavbarItems>
    </template>

    <div class="mx-auto max-w-7xl">
      <div class="mb-6">
        <ITextDisplay text="Builder Studio" />
      </div>

      <ICard>
        <ITable bleed>
          <ITableHead class="bg-neutral-50 dark:bg-neutral-500/10">
            <ITableRow>
              <ITableHeader>Name</ITableHeader>
              <ITableHeader>Module</ITableHeader>
              <ITableHeader>Resource</ITableHeader>
              <ITableHeader>Status</ITableHeader>
              <ITableHeader></ITableHeader>
            </ITableRow>
          </ITableHead>

          <ITableBody>
            <ITableRow v-for="definition in definitions" :key="definition.id">
              <ITableCell>
                <span class="font-medium text-neutral-900 dark:text-white">
                  {{ definition.name }}
                </span>
              </ITableCell>
              <ITableCell>{{ definition.module_name || '-' }}</ITableCell>
              <ITableCell>{{ definition.resource_name || '-' }}</ITableCell>
              <ITableCell>
                <IBadge :text="definition.status" variant="neutral" />
              </ITableCell>
              <ITableCell class="text-right">
                <IButton
                  basic
                  icon="ChevronRight"
                  text="Open"
                  :to="{
                    name: 'builder-definition-view',
                    params: { id: definition.id },
                  }"
                />
              </ITableCell>
            </ITableRow>

            <ITableRow v-if="!loading && definitions.length === 0">
              <ITableCell colspan="5">
                <IText text="No builder definitions yet." />
              </ITableCell>
            </ITableRow>
          </ITableBody>
        </ITable>
      </ICard>
    </div>
  </MainLayout>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'

import { usePageTitle } from '@/Core/composables/usePageTitle'

import { createDefinition, fetchDefinitions } from '../services/builderApi'
import { neutralDefinition } from '../fixtures/neutralDefinition'

const router = useRouter()
const loading = ref(false)
const creating = ref(false)
const definitions = ref([])

usePageTitle('Builder Studio')

onMounted(loadDefinitions)

async function loadDefinitions() {
  loading.value = true

  try {
    const { data } = await fetchDefinitions()
    definitions.value = Array.isArray(data.data) ? data.data : data
  } finally {
    loading.value = false
  }
}

async function createDraft() {
  creating.value = true

  try {
    const { data } = await createDefinition({
      name: 'Custom Records Draft',
      definition_json: neutralDefinition(),
    })

    await router.push({
      name: 'builder-definition-view',
      params: { id: data.id },
    })
  } finally {
    creating.value = false
  }
}
</script>
