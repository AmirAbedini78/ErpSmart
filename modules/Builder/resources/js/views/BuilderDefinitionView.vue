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
      <IAlert v-if="apiError" class="mb-6" variant="danger">
        <IAlertBody>{{ apiError }}</IAlertBody>
      </IAlert>

      <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <ITextDisplay :text="definition.name" />
          <IText class="mt-1" :text="definition.resource_name || 'draft'" />
        </div>

        <BuilderStatusBadge :status="definition.status" />
      </div>

      <div class="grid gap-6 xl:grid-cols-12">
        <div class="space-y-6 xl:col-span-8">
          <ICard>
            <ICardHeader>
              <ICardHeading text="Demo flow" />
            </ICardHeader>

            <ICardBody>
              <ol class="grid gap-2 text-sm md:grid-cols-2">
                <li v-for="step in demoFlowSteps" :key="step" class="flex gap-2">
                  <span class="text-neutral-400">•</span>
                  <span>{{ step }}</span>
                </li>
              </ol>
            </ICardBody>
          </ICard>

          <BuilderModuleIdentityForm
            :definition="definitionJson"
            @changed="handleVisualChange"
          />

          <BuilderFieldsEditor
            :definition="definitionJson"
            @changed="handleVisualChange"
          />

          <BuilderCapabilitiesEditor
            :definition="definitionJson"
            @changed="handleVisualChange"
          />

          <BuilderRelationsEditor
            :definition="definitionJson"
            @changed="handleVisualChange"
          />

          <BuilderRawJsonEditor
            v-model="definitionText"
            :error="jsonError"
            @apply="applyRawJson"
            @format="formatRawJson"
          />
        </div>

        <div class="xl:col-span-4">
          <div class="space-y-6 xl:sticky xl:top-6">
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

            <BuilderValidationPreviewPanel
              :saving="saving"
              :validating="validating"
              :previewing="previewing"
              :validation-report="validationReport || definition.last_validation_report_json"
              :preview-run="previewRun"
              :preview-manifest="definition.last_preview_manifest_json"
              @save="saveDefinition"
              @validate="runValidation"
              @preview="runPreview"
            />
          </div>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'

import { usePageTitle } from '@/Core/composables/usePageTitle'

import BuilderCapabilitiesEditor from '../components/BuilderCapabilitiesEditor.vue'
import BuilderFieldsEditor from '../components/BuilderFieldsEditor.vue'
import BuilderModuleIdentityForm from '../components/BuilderModuleIdentityForm.vue'
import BuilderRawJsonEditor from '../components/BuilderRawJsonEditor.vue'
import BuilderRelationsEditor from '../components/BuilderRelationsEditor.vue'
import BuilderStatusBadge from '../components/BuilderStatusBadge.vue'
import BuilderValidationPreviewPanel from '../components/BuilderValidationPreviewPanel.vue'
import {
  getDefinition,
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
const definitionJson = ref(null)
const definitionText = ref('')
const validationReport = ref(null)
const previewRun = ref(null)
const jsonError = ref(null)
const apiError = ref(null)
const demoFlowSteps = [
  'Edit identity',
  'Add fields',
  'Toggle capabilities',
  'Add relations if needed',
  'Save',
  'Validate',
  'Preview',
]

usePageTitle('Builder Definition')

onMounted(loadDefinition)

async function loadDefinition() {
  loading.value = true

  try {
    const { data } = await getDefinition(route.params.id)
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
  apiError.value = null

  try {
    const { data } = await updateDefinition(definition.value.id, {
      definition_json: parsed,
    })
    setDefinition(data)
    Innoclapps.success('Builder definition saved.')
  } catch (error) {
    apiError.value = errorMessage(error)
  } finally {
    saving.value = false
  }
}

async function runValidation() {
  validating.value = true
  apiError.value = null

  try {
    const { data } = await validateDefinition(definition.value.id)
    setDefinition(data.definition)
    validationReport.value = data.validation_report || data.report
  } catch (error) {
    apiError.value = errorMessage(error)
  } finally {
    validating.value = false
  }
}

async function runPreview() {
  previewing.value = true
  apiError.value = null

  try {
    const { data } = await previewDefinition(definition.value.id)
    setDefinition(data.definition)
    previewRun.value = data.preview_run
    validationReport.value = data.validation_report || data.report || validationReport.value
  } catch (error) {
    const response = error.response?.data

    if (response?.definition) {
      setDefinition(response.definition)
    }

    validationReport.value = response?.validation_report || response?.report || validationReport.value
    apiError.value = response?.message || errorMessage(error)
  } finally {
    previewing.value = false
  }
}

function handleVisualChange() {
  jsonError.value = null
  normalizeDefinition(definitionJson.value)
  definitionText.value = stringify(definitionJson.value)
}

function applyRawJson() {
  const parsed = parseDefinitionText()

  if (!parsed) {
    return
  }

  definitionJson.value = normalizeDefinition(parsed)
  definitionText.value = stringify(definitionJson.value)
}

function formatRawJson() {
  const parsed = parseDefinitionText()

  if (!parsed) {
    return
  }

  definitionText.value = stringify(parsed)
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
  definitionJson.value = normalizeDefinition(clone(value.definition_json || {}))
  definitionText.value = stringify(definitionJson.value)
  validationReport.value = value.last_validation_report_json
  previewRun.value = null
}

function normalizeDefinition(value) {
  value.schemaVersion ||= 1
  value.module ||= {}
  value.resource ||= {}
  value.fields ||= []
  value.relations ||= []
  value.capabilities ||= {}
  value.permissions ||= {}
  value.frontend ||= {}
  value.verifier ||= { generate: true }
  value.detailPage ||= { panels: [], tabs: [] }
  value.table ||= {}

  value.resource.hasDetailView = Boolean(value.resource.hasDetailView)
  value.capabilities.hasDetailView = Boolean(
    value.capabilities.hasDetailView ?? value.resource.hasDetailView
  )

  value.fields = value.fields.map(field => {
    field.visibility ||= {}
    field.rules ||= []
    field.creationRules ||= []
    field.updateRules ||= []
    field.table ||= {}

    return field
  })

  return value
}

function stringify(value) {
  return JSON.stringify(value, null, 2)
}

function clone(value) {
  return JSON.parse(JSON.stringify(value))
}

function errorMessage(error) {
  return error.response?.data?.message || error.message || 'Builder request failed.'
}
</script>
