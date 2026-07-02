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
            <IText text="Archived" />
            <ITextDark class="mt-1 text-2xl font-semibold" :text="String(summary.archived)" />
          </ICardBody>
        </ICard>
      </div>

      <div class="mb-4 flex flex-wrap gap-2">
        <IButton
          :variant="statusFilter === 'active' ? 'primary' : 'secondary'"
          text="Active"
          @click="statusFilter = 'active'"
        />
        <IButton
          :variant="statusFilter === 'archived' ? 'primary' : 'secondary'"
          text="Archived"
          @click="statusFilter = 'archived'"
        />
        <IButton
          :variant="statusFilter === 'all' ? 'primary' : 'secondary'"
          text="All"
          @click="statusFilter = 'all'"
        />
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
            <ITableRow v-for="definition in filteredDefinitions" :key="definition.id">
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
                <div class="flex flex-wrap justify-end gap-2">
                  <IButton
                    basic
                    icon="ChevronRight"
                    text="Open"
                    :to="{
                      name: 'builder-definition-view',
                      params: { id: definition.id },
                    }"
                  />

                  <IButton
                    v-if="definition.status !== 'archived'"
                    basic
                    icon="ArchiveBox"
                    text="Archive"
                    :loading="lifecycleAction === `archive-${definition.id}`"
                    @click="archiveDraft(definition)"
                  />

                  <IButton
                    v-if="definition.status === 'archived'"
                    basic
                    icon="ArrowUturnLeft"
                    text="Restore"
                    :loading="lifecycleAction === `restore-${definition.id}`"
                    @click="restoreDraft(definition)"
                  />

                  <IButton
                    v-if="canDeleteDraft(definition)"
                    basic
                    variant="danger"
                    icon="Trash"
                    text="Delete draft"
                    :loading="lifecycleAction === `delete-${definition.id}`"
                    @click="deleteDraft(definition)"
                  />
                </div>
              </ITableCell>
            </ITableRow>

            <ITableRow v-if="!loading && filteredDefinitions.length === 0">
              <ITableCell colspan="7">
                <div class="flex flex-col items-center gap-3 py-12 text-center">
                  <ITextDark
                    class="font-medium"
                    :text="emptyStateTitle"
                  />
                  <IText
                    class="max-w-xl"
                    :text="emptyStateText"
                  />
                  <IButton
                    v-if="definitions.length === 0"
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
import {
  archiveDefinition,
  createDefinition,
  deleteDefinition,
  fetchDefinitions,
  restoreDefinition,
} from '../services/builderApi'
import { neutralDefinition } from '../fixtures/neutralDefinition'

const router = useRouter()
const loading = ref(false)
const creating = ref(false)
const definitions = ref([])
const statusFilter = ref('active')
const lifecycleAction = ref(null)
const summary = computed(() => ({
  total: definitions.value.length,
  draft: countByStatus('draft'),
  validated: countByStatus('validated'),
  archived: countByStatus('archived'),
}))
const filteredDefinitions = computed(() => {
  if (statusFilter.value === 'archived') {
    return definitions.value.filter(definition => definition.status === 'archived')
  }

  if (statusFilter.value === 'all') {
    return definitions.value
  }

  return definitions.value.filter(definition => definition.status !== 'archived')
})
const emptyStateTitle = computed(() =>
  definitions.value.length === 0 ? 'No builder definitions yet.' : 'No definitions match this filter.'
)
const emptyStateText = computed(() =>
  definitions.value.length === 0
    ? 'Start with a neutral draft, then edit identity, fields, capabilities, and relations before running validation and preview.'
    : 'Switch filters to review active, archived, or all Builder definitions.'
)

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

async function archiveDraft(definition) {
  lifecycleAction.value = `archive-${definition.id}`

  try {
    const { data } = await archiveDefinition(definition.id)
    replaceDefinition(data.definition)
    Innoclapps.success('Builder definition archived.')
  } finally {
    lifecycleAction.value = null
  }
}

async function restoreDraft(definition) {
  lifecycleAction.value = `restore-${definition.id}`

  try {
    const { data } = await restoreDefinition(definition.id)
    replaceDefinition(data.definition)
    Innoclapps.success('Builder definition restored.')
  } finally {
    lifecycleAction.value = null
  }
}

async function deleteDraft(definition) {
  const confirmed = window.confirm(
    'This deletes only the Builder draft/control-plane records. It does not delete runtime modules or database tables.'
  )

  if (!confirmed) {
    return
  }

  lifecycleAction.value = `delete-${definition.id}`

  try {
    await deleteDefinition(definition.id)
    definitions.value = definitions.value.filter(item => item.id !== definition.id)
    Innoclapps.success('Builder draft deleted. No runtime modules or database tables were changed.')
  } finally {
    lifecycleAction.value = null
  }
}

function canDeleteDraft(definition) {
  return [
    'draft',
    'validated',
    'validation_failed',
    'previewed',
    'preview_failed',
    'archived',
  ].includes(definition.status)
}

function replaceDefinition(nextDefinition) {
  const index = definitions.value.findIndex(definition => definition.id === nextDefinition.id)

  if (index === -1) {
    definitions.value.unshift(nextDefinition)

    return
  }

  definitions.value[index] = nextDefinition
}
</script>
