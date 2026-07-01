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
      <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <ITextDisplay text="Builder Studio" />
          <IText
            class="mt-2 max-w-3xl"
            text="Create a draft, edit visually, validate, and preview. Publish is not available yet."
          />
          <IText
            class="mt-1 max-w-3xl"
            text="Validate and Preview only. No runtime module will be generated from the UI in this MVP."
          />
        </div>

        <IButton
          class="lg:hidden"
          variant="primary"
          icon="PlusSolid"
          text="Create draft"
          :loading="creating"
          @click="createDraft"
        />
      </div>

      <div class="mb-6 grid gap-4 md:grid-cols-4">
        <ICard>
          <ICardBody>
            <IText text="Total definitions" />
            <ITextDark class="mt-1 text-2xl font-semibold" :text="String(summary.total)" />
          </ICardBody>
        </ICard>
        <ICard>
          <ICardBody>
            <IText text="Draft" />
            <ITextDark class="mt-1 text-2xl font-semibold" :text="String(summary.draft)" />
          </ICardBody>
        </ICard>
        <ICard>
          <ICardBody>
            <IText text="Validated" />
            <ITextDark class="mt-1 text-2xl font-semibold" :text="String(summary.validated)" />
          </ICardBody>
        </ICard>
        <ICard>
          <ICardBody>
            <IText text="Previewed" />
            <ITextDark class="mt-1 text-2xl font-semibold" :text="String(summary.previewed)" />
          </ICardBody>
        </ICard>
      </div>

      <ICard>
        <ITable bleed>
          <ITableHead class="bg-neutral-50 dark:bg-neutral-500/10">
            <ITableRow>
              <ITableHeader>Name</ITableHeader>
              <ITableHeader>Module</ITableHeader>
              <ITableHeader>Entity</ITableHeader>
              <ITableHeader>Resource</ITableHeader>
              <ITableHeader>Status</ITableHeader>
              <ITableHeader>Updated</ITableHeader>
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
              <ITableCell>{{ definition.entity_name || '-' }}</ITableCell>
              <ITableCell>{{ definition.resource_name || '-' }}</ITableCell>
              <ITableCell>
                <BuilderStatusBadge :status="definition.status" />
              </ITableCell>
              <ITableCell>
                {{ definition.updated_at || definition.created_at || '-' }}
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
              <ITableCell colspan="7">
                <div class="flex flex-col items-center gap-3 py-12 text-center">
                  <ITextDark
                    class="font-medium"
                    text="No builder definitions yet."
                  />
                  <IText
                    class="max-w-xl"
                    text="Start with a neutral draft, then edit identity, fields, capabilities, and relations before running validation and preview."
                  />
                  <IButton
                    variant="primary"
                    icon="PlusSolid"
                    text="Create draft"
                    :loading="creating"
                    @click="createDraft"
                  />
                </div>
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
import { computed } from 'vue'
import { useRouter } from 'vue-router'

import { usePageTitle } from '@/Core/composables/usePageTitle'

import BuilderStatusBadge from '../components/BuilderStatusBadge.vue'
import { createDefinition, fetchDefinitions } from '../services/builderApi'
import { neutralDefinition } from '../fixtures/neutralDefinition'

const router = useRouter()
const loading = ref(false)
const creating = ref(false)
const definitions = ref([])
const summary = computed(() => ({
  total: definitions.value.length,
  draft: countByStatus('draft'),
  validated: countByStatus('validated'),
  previewed: countByStatus('previewed'),
}))

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

function countByStatus(status) {
  return definitions.value.filter(definition => definition.status === status).length
}
</script>
