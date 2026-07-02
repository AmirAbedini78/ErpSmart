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

        <IButton
          v-if="definition.status !== 'archived'"
          basic
          icon="ArchiveBox"
          text="Archive"
          :loading="lifecycleAction === 'archive'"
          @click="archiveCurrentDefinition"
        />

        <IButton
          v-if="definition.status === 'archived'"
          basic
          icon="ArrowUturnLeft"
          text="Restore"
          :loading="lifecycleAction === 'restore'"
          @click="restoreCurrentDefinition"
        />

        <IButton
          v-if="canDeleteCurrentDefinition"
          basic
          variant="danger"
          icon="Trash"
          text="Delete draft"
          :loading="lifecycleAction === 'delete'"
          @click="deleteCurrentDefinition"
        />
      </NavbarItems>
    </template>

    <div v-if="definition" class="mx-auto max-w-7xl">
      <IAlert v-if="apiError" class="mb-6" variant="danger">
        <IAlertBody>{{ apiError }}</IAlertBody>
      </IAlert>

      <IAlert v-if="definition.status === 'archived'" class="mb-6" variant="warning">
        <IAlertBody>
          This Builder definition is archived. Restore it before continuing active draft work. Archive and restore do not change runtime modules, files, migrations, or database tables.
        </IAlertBody>
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
          <ICard id="demo-flow">
            <ICardHeader>
              <ICardHeading text="Demo flow" />
            </ICardHeader>

            <ICardBody>
              <IAlert class="mb-4" variant="warning">
                <IAlertBody>
                  Preview-only MVP. Validate and Preview are available; Publish is intentionally absent. No runtime writes are performed from the UI.
                </IAlertBody>
              </IAlert>

              <ol class="grid gap-2 text-sm md:grid-cols-2">
                <li v-for="step in demoFlowSteps" :key="step" class="flex gap-2">
                  <span class="text-neutral-400">•</span>
                  <span>{{ step }}</span>
                </li>
              </ol>
            </ICardBody>
          </ICard>

          <BuilderModuleIdentityForm
            id="identity"
            :definition="definitionJson"
            @changed="handleVisualChange"
          />

          <BuilderFieldsEditor
            id="fields"
            :definition="definitionJson"
            @changed="handleVisualChange"
          />

          <BuilderFormLayoutEditor
            id="form-layout"
            :definition="definitionJson"
            @changed="handleVisualChange"
          />

          <BuilderAutomationEditor
            id="automation"
            :definition="definitionJson"
            @changed="handleVisualChange"
          />

          <BuilderCapabilitiesEditor
            id="capabilities"
            :definition="definitionJson"
            @changed="handleVisualChange"
          />

          <BuilderRelationsEditor
            id="relations"
            :definition="definitionJson"
            @changed="handleVisualChange"
          />

          <BuilderRawJsonEditor
            id="raw-json"
            v-model="definitionText"
            :error="jsonError"
            @apply="applyRawJson"
            @format="formatRawJson"
          />
        </div>

        <div class="xl:col-span-4">
          <div class="space-y-6 xl:sticky xl:top-6">
            <BuilderDefinitionSummary
              :definition-json="definitionJson"
              :status="definition.status"
            />

            <ICard>
              <ICardHeader>
                <ICardHeading text="Section Navigation" />
              </ICardHeader>

              <ICardBody>
                <nav class="grid gap-2 text-sm">
                  <a
                    v-for="section in sectionNavigation"
                    :key="section.id"
                    class="text-primary-600 hover:text-primary-700 dark:text-primary-400"
                    :href="`#${section.id}`"
                  >
                    {{ section.label }}
                  </a>
                </nav>
              </ICardBody>
            </ICard>

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
              id="validate-preview"
              :saving="saving"
              :validating="validating"
              :previewing="previewing"
              :readiness-analyzing="readinessAnalyzing"
              :dry-run-generating="dryRunGenerating"
              :validation-report="validationReport || definition.last_validation_report_json"
              :preview-run="previewRun"
              :preview-manifest="definition.last_preview_manifest_json"
              :publish-readiness-report="publishReadinessReport"
              :publish-dry-run-report="publishDryRunReport"
              @save="saveDefinition"
              @validate="runValidation"
              @preview="runPreview"
              @analyze-readiness="runReadinessAnalysis"
              @generate-dry-run="runDryRunGeneration"
            />
          </div>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { usePageTitle } from '@/Core/composables/usePageTitle'

import BuilderAutomationEditor from '../components/BuilderAutomationEditor.vue'
import BuilderCapabilitiesEditor from '../components/BuilderCapabilitiesEditor.vue'
import BuilderDefinitionSummary from '../components/BuilderDefinitionSummary.vue'
import BuilderFieldsEditor from '../components/BuilderFieldsEditor.vue'
import BuilderFormLayoutEditor from '../components/BuilderFormLayoutEditor.vue'
import BuilderModuleIdentityForm from '../components/BuilderModuleIdentityForm.vue'
import BuilderRawJsonEditor from '../components/BuilderRawJsonEditor.vue'
import BuilderRelationsEditor from '../components/BuilderRelationsEditor.vue'
import BuilderStatusBadge from '../components/BuilderStatusBadge.vue'
import BuilderValidationPreviewPanel from '../components/BuilderValidationPreviewPanel.vue'
import {
  analyzePublishReadiness,
  archiveDefinition,
  deleteDefinition,
  generatePublishDryRun,
  getDefinition,
  previewDefinition,
  restoreDefinition,
  updateDefinition,
  validateDefinition,
} from '../services/builderApi'

const route = useRoute()
const router = useRouter()
const loading = ref(false)
const saving = ref(false)
const validating = ref(false)
const previewing = ref(false)
const readinessAnalyzing = ref(false)
const dryRunGenerating = ref(false)
const lifecycleAction = ref(null)
const definition = ref(null)
const definitionJson = ref(null)
const definitionText = ref('')
const validationReport = ref(null)
const previewRun = ref(null)
const publishReadinessReport = ref(null)
const publishDryRunReport = ref(null)
const jsonError = ref(null)
const apiError = ref(null)
const demoFlowSteps = [
  'Edit identity',
  'Add fields',
  'Design Form Layout metadata',
  'Design Automation metadata',
  'Toggle capabilities',
  'Add relations if needed',
  'Save',
  'Validate',
  'Preview',
]
const sectionNavigation = [
  { id: 'demo-flow', label: 'Demo Flow' },
  { id: 'identity', label: 'Identity' },
  { id: 'fields', label: 'Fields' },
  { id: 'form-layout', label: 'Form Layout' },
  { id: 'automation', label: 'Automation' },
  { id: 'capabilities', label: 'Capabilities' },
  { id: 'relations', label: 'Relations' },
  { id: 'raw-json', label: 'Raw JSON' },
  { id: 'validate-preview', label: 'Validate & Preview' },
]
const canDeleteCurrentDefinition = computed(() =>
  [
    'draft',
    'validated',
    'validation_failed',
    'previewed',
    'preview_failed',
    'archived',
  ].includes(definition.value?.status)
)

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

async function runReadinessAnalysis() {
  readinessAnalyzing.value = true
  apiError.value = null

  try {
    const { data } = await analyzePublishReadiness(definition.value.id)
    publishReadinessReport.value = data
    Innoclapps.success('Publish readiness analysis completed. No runtime writes were performed.')
  } catch (error) {
    apiError.value = errorMessage(error)
  } finally {
    readinessAnalyzing.value = false
  }
}

async function runDryRunGeneration() {
  dryRunGenerating.value = true
  apiError.value = null

  try {
    const { data } = await generatePublishDryRun(definition.value.id)
    publishDryRunReport.value = data
    Innoclapps.success('Publish dry run generated under storage. No runtime writes were performed.')
  } catch (error) {
    apiError.value = errorMessage(error)
  } finally {
    dryRunGenerating.value = false
  }
}

async function archiveCurrentDefinition() {
  lifecycleAction.value = 'archive'
  apiError.value = null

  try {
    const { data } = await archiveDefinition(definition.value.id)
    setDefinition(data.definition)
    Innoclapps.success('Builder definition archived.')
  } catch (error) {
    apiError.value = errorMessage(error)
  } finally {
    lifecycleAction.value = null
  }
}

async function restoreCurrentDefinition() {
  lifecycleAction.value = 'restore'
  apiError.value = null

  try {
    const { data } = await restoreDefinition(definition.value.id)
    setDefinition(data.definition)
    Innoclapps.success('Builder definition restored.')
  } catch (error) {
    apiError.value = errorMessage(error)
  } finally {
    lifecycleAction.value = null
  }
}

async function deleteCurrentDefinition() {
  const confirmed = window.confirm(
    'This deletes only the Builder draft/control-plane records. It does not delete runtime modules or database tables.'
  )

  if (!confirmed) {
    return
  }

  lifecycleAction.value = 'delete'
  apiError.value = null

  try {
    await deleteDefinition(definition.value.id)
    Innoclapps.success('Builder draft deleted. No runtime modules or database tables were changed.')
    await router.push({ name: 'builder-definitions-index' })
  } catch (error) {
    apiError.value = errorMessage(error)
  } finally {
    lifecycleAction.value = null
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
  publishReadinessReport.value = null
  publishDryRunReport.value = null
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
  value.formLayout ||= {}
  value.automation ||= {}

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

  normalizeFormLayout(value.formLayout)
  normalizeAutomation(value.automation)

  return value
}

function normalizeFormLayout(formLayout) {
  formLayout.enabled = Boolean(formLayout.enabled ?? false)
  formLayout.mode ||= 'standard'
  formLayout.sections ||= []
  formLayout.stepper ||= {}
  formLayout.stepper.enabled = Boolean(formLayout.stepper.enabled ?? false)
  formLayout.stepper.steps ||= []
  formLayout.conditions ||= []

  formLayout.sections.forEach((section, index) => {
    section.id ||= `section_${index + 1}`
    section.label ||= `Section ${index + 1}`
    section.description ||= ''
    section.order ||= index + 1
    section.modes ||= ['create', 'update', 'detail']
    section.columns ||= 1
    section.fields ||= []

    section.fields.forEach((field, fieldIndex) => {
      field.order ||= fieldIndex + 1
      field.width ||= 'full'
      field.requiredOverride ??= null
      field.readonlyOn ||= []
      field.hiddenOn ||= []
      field.helpText ||= ''
    })
  })

  formLayout.stepper.steps.forEach((step, index) => {
    step.id ||= `step_${index + 1}`
    step.label ||= `Step ${index + 1}`
    step.sectionIds ||= []
    step.order ||= index + 1
  })

  formLayout.conditions.forEach((condition, index) => {
    condition.id ||= `condition_${index + 1}`
    condition.targetField ||= ''
    condition.operator ||= 'equals'
    condition.value ??= ''
    condition.effect ||= 'show'
    condition.appliesTo ||= ['create', 'update']
  })
}

function normalizeAutomation(automation) {
  automation.enabled = Boolean(automation.enabled ?? false)
  automation.workflows ||= []

  automation.workflows.forEach((workflow, workflowIndex) => {
    workflow.id ||= `workflow_${workflowIndex + 1}`
    workflow.name ||= `Workflow ${workflowIndex + 1}`
    workflow.description ||= ''
    workflow.enabled = Boolean(workflow.enabled ?? true)
    workflow.trigger ||= {}
    workflow.trigger.type ||= 'record_created'
    workflow.trigger.field ||= ''
    workflow.trigger.value ??= ''
    workflow.trigger.modes ||= ['create']
    workflow.conditions ||= []
    workflow.actions ||= []

    workflow.conditions.forEach((condition, conditionIndex) => {
      condition.id ||= `condition_${conditionIndex + 1}`
      condition.field ||= ''
      condition.operator ||= 'equals'
      condition.value ??= ''
      condition.join ||= 'and'
    })

    workflow.actions.forEach((action, actionIndex) => {
      action.id ||= `action_${actionIndex + 1}`
      action.type ||= 'create_task'
      action.enabled = Boolean(action.enabled ?? true)
      action.label ||= `Action ${actionIndex + 1}`
      action.order ||= actionIndex + 1
      action.config ||= {}
      action.config.taskTitle ??= ''
      action.config.taskDueInDays ??= 1
      action.config.emailTo ??= ''
      action.config.emailSubject ??= ''
      action.config.emailTemplate ??= ''
      action.config.notificationMessage ??= ''
      action.config.approvalRole ??= ''
      action.config.webhookUrl ??= ''
    })
  })
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
