<template>
  <MainLayout :overlay="loading">
    <template #actions>
      <NavbarSeparator class="hidden lg:block" />

      <NavbarItems>
        <IButton
          basic
          icon="ChevronLeft"
          text="Back"
          :to="{ name: 'builder-definitions-index' }"
        />

        <IButton
          icon="Check"
          text="Save"
          :loading="saving"
          @click="saveDefinition"
        />

        <IButton
          icon="CheckCircle"
          text="Validate"
          :loading="validating"
          @click="runValidation"
        />

        <IButton
          variant="primary"
          icon="Eye"
          text="Preview"
          :loading="previewing"
          @click="runPreview"
        />
      </NavbarItems>
    </template>

    <div v-if="definition" class="mx-auto max-w-7xl">
      <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <ITextDisplay :text="definition.name" />
          <IText class="mt-1" :text="definition.resource_name || 'draft'" />
        </div>

        <IBadge :text="definition.status" variant="neutral" />
      </div>

      <div class="grid gap-6 lg:grid-cols-12">
        <div class="lg:col-span-7">
          <ICard>
            <ICardHeader>
              <ICardHeading text="Definition JSON" />
            </ICardHeader>

            <ICardBody>
              <IFormTextarea
                v-model="definitionText"
                rows="28"
                class="font-mono text-sm"
              />

              <IAlert v-if="jsonError" class="mt-3" variant="danger">
                <IAlertBody>{{ jsonError }}</IAlertBody>
              </IAlert>
            </ICardBody>
          </ICard>
        </div>

        <div class="space-y-6 lg:col-span-5">
          <ICard>
            <ICardHeader>
              <ICardHeading text="Metadata" />
            </ICardHeader>

            <ICardBody>
              <dl class="space-y-3 text-sm">
                <div class="flex justify-between gap-4">
                  <dt class="text-neutral-500 dark:text-neutral-400">Module</dt>
                  <dd class="font-medium">{{ definition.module_name || '-' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                  <dt class="text-neutral-500 dark:text-neutral-400">Entity</dt>
                  <dd class="font-medium">{{ definition.entity_name || '-' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                  <dt class="text-neutral-500 dark:text-neutral-400">Checksum</dt>
                  <dd class="max-w-56 truncate font-mono text-xs">
                    {{ definition.checksum || '-' }}
                  </dd>
                </div>
              </dl>
            </ICardBody>
          </ICard>

          <ICard>
            <ICardHeader>
              <ICardHeading text="Validation Report" />
            </ICardHeader>

            <ICardBody>
              <pre class="max-h-72 overflow-auto whitespace-pre-wrap text-xs">{{ formattedValidationReport }}</pre>
            </ICardBody>
          </ICard>

          <ICard>
            <ICardHeader>
              <ICardHeading text="Preview Output" />
            </ICardHeader>

            <ICardBody>
              <pre class="max-h-96 overflow-auto whitespace-pre-wrap text-xs">{{ formattedPreviewOutput }}</pre>
            </ICardBody>
          </ICard>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'

import { usePageTitle } from '@/Core/composables/usePageTitle'

import {
  fetchDefinition,
  previewDefinition,
  updateDefinition,
  validateDefinition,
} from '../services/builderApi'

const route = useRoute()
const loading = ref(false)
const saving = ref(false)
const validating = ref(false)
const previewing = ref(false)
const definition = ref(null)
const definitionText = ref('')
const validationReport = ref(null)
const previewRun = ref(null)
const jsonError = ref(null)

usePageTitle('Builder Definition')

const formattedValidationReport = computed(() =>
  formatJson(validationReport.value || definition.value?.last_validation_report_json)
)

const formattedPreviewOutput = computed(() => {
  if (previewRun.value?.output_text) {
    return previewRun.value.output_text
  }

  return formatJson(definition.value?.last_preview_manifest_json)
})

onMounted(loadDefinition)

async function loadDefinition() {
  loading.value = true

  try {
    const { data } = await fetchDefinition(route.params.id)
    setDefinition(data)
  } finally {
    loading.value = false
  }
}

async function saveDefinition() {
  const parsed = parseDefinitionText()

  if (!parsed) {
    return
  }

  saving.value = true

  try {
    const { data } = await updateDefinition(definition.value.id, {
      definition_json: parsed,
    })
    setDefinition(data)
    Innoclapps.success('Builder definition saved.')
  } finally {
    saving.value = false
  }
}

async function runValidation() {
  validating.value = true

  try {
    const { data } = await validateDefinition(definition.value.id)
    setDefinition(data.definition)
    validationReport.value = data.report
  } finally {
    validating.value = false
  }
}

async function runPreview() {
  previewing.value = true

  try {
    const { data } = await previewDefinition(definition.value.id)
    setDefinition(data.definition)
    previewRun.value = data.preview_run
  } finally {
    previewing.value = false
  }
}

function parseDefinitionText() {
  try {
    jsonError.value = null

    return JSON.parse(definitionText.value)
  } catch (error) {
    jsonError.value = error.message

    return null
  }
}

function setDefinition(value) {
  definition.value = value
  definitionText.value = JSON.stringify(value.definition_json || {}, null, 2)
  validationReport.value = value.last_validation_report_json
  previewRun.value = null
}

function formatJson(value) {
  if (!value) {
    return 'Not run yet.'
  }

  return JSON.stringify(value, null, 2)
}
</script>
