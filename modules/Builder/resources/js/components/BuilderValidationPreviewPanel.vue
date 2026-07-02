<template>
  <div class="space-y-6">
    <ICard>
      <ICardHeader>
        <ICardHeading text="Actions" />
      </ICardHeader>

      <ICardBody class="space-y-3">
        <IButton
          class="w-full justify-center"
          icon="Check"
          text="Save"
          :loading="saving"
          @click="$emit('save')"
        />
        <IButton
          class="w-full justify-center"
          icon="CheckCircle"
          text="Validate"
          :loading="validating"
          @click="$emit('validate')"
        />
        <IButton
          class="w-full justify-center"
          variant="primary"
          icon="Eye"
          text="Preview"
          :loading="previewing"
          @click="$emit('preview')"
        />
        <IButton
          class="w-full justify-center"
          icon="CheckCircle"
          text="Analyze Publish Readiness"
          :loading="readinessAnalyzing"
          @click="$emit('analyze-readiness')"
        />
        <IButton
          class="w-full justify-center"
          icon="DocumentText"
          text="Generate Publish Dry Run"
          :loading="dryRunGenerating"
          @click="$emit('generate-dry-run')"
        />
      </ICardBody>
    </ICard>

    <ICard>
      <ICardHeader>
        <ICardHeading text="Validation Report" />
      </ICardHeader>

      <ICardBody class="space-y-3">
        <div class="flex items-center justify-between gap-3">
          <IText text="Last validation" />
          <BuilderStatusBadge :status="validationStatus" />
        </div>

        <IAlert v-if="validationReport?.warnings?.length" variant="warning">
          <IAlertBody>
            <ul class="list-disc space-y-1 pl-5">
              <li v-for="warning in validationReport.warnings" :key="warning">
                {{ warning }}
              </li>
            </ul>
          </IAlertBody>
        </IAlert>

        <IAlert v-if="validationReport?.errors?.length" variant="danger">
          <IAlertBody>
            <ul class="list-disc space-y-1 pl-5">
              <li v-for="error in validationReport.errors" :key="error">
                {{ error }}
              </li>
            </ul>
          </IAlertBody>
        </IAlert>

        <pre class="max-h-72 overflow-auto whitespace-pre-wrap rounded-md bg-neutral-50 p-3 text-xs dark:bg-neutral-900">{{ formattedValidationReport }}</pre>
      </ICardBody>
    </ICard>

    <ICard>
      <ICardHeader>
        <ICardHeading text="Preview Output" />
      </ICardHeader>

      <ICardBody class="space-y-3">
        <div class="flex items-center justify-between gap-3">
          <IText text="Last preview" />
          <BuilderStatusBadge :status="previewStatus" />
        </div>

        <IText
          v-if="previewPath"
          class="break-all text-xs"
          :text="previewPath"
        />

        <pre class="max-h-96 overflow-auto whitespace-pre-wrap rounded-md bg-neutral-50 p-3 text-xs dark:bg-neutral-900">{{ formattedPreviewOutput }}</pre>
      </ICardBody>
    </ICard>

    <ICard>
      <ICardHeader>
        <ICardHeading text="Publish Readiness Analysis" />
      </ICardHeader>

      <ICardBody class="space-y-3">
        <IAlert variant="info">
          <IAlertBody>
            Analysis only. No runtime files, modules, migrations, tables, or publish actions are performed.
          </IAlertBody>
        </IAlert>

        <div class="flex items-center justify-between gap-3">
          <IText text="Readiness status" />
          <BuilderStatusBadge :status="publishReadinessReport?.status || 'not_run'" />
        </div>

        <div v-if="publishReadinessReport" class="space-y-3">
          <div class="grid gap-3 text-sm">
            <div class="flex justify-between gap-4">
              <span class="text-neutral-500 dark:text-neutral-400">writes_performed</span>
              <span class="font-mono">{{ publishReadinessReport.writes_performed }}</span>
            </div>
            <div class="flex justify-between gap-4">
              <span class="text-neutral-500 dark:text-neutral-400">publish_executed</span>
              <span class="font-mono">{{ String(publishReadinessReport.publish_executed) }}</span>
            </div>
            <div class="flex justify-between gap-4">
              <span class="text-neutral-500 dark:text-neutral-400">runtime_module_effect</span>
              <span class="font-mono">{{ publishReadinessReport.runtime_module_effect }}</span>
            </div>
            <div class="flex justify-between gap-4">
              <span class="text-neutral-500 dark:text-neutral-400">diagnostic_artifact_path</span>
              <span class="break-all text-right font-mono text-xs">{{ publishReadinessReport.diagnostic_artifact_path || '-' }}</span>
            </div>
          </div>

          <IAlert v-if="publishReadinessReport.blockers?.length" variant="danger">
            <IAlertBody>
              <div class="mb-1 font-medium">Blockers</div>
              <ul class="list-disc space-y-1 pl-5">
                <li v-for="blocker in publishReadinessReport.blockers" :key="blocker">
                  {{ blocker }}
                </li>
              </ul>
            </IAlertBody>
          </IAlert>

          <IAlert v-if="publishReadinessReport.warnings?.length" variant="warning">
            <IAlertBody>
              <div class="mb-1 font-medium">Warnings</div>
              <ul class="list-disc space-y-1 pl-5">
                <li v-for="warning in publishReadinessReport.warnings" :key="warning">
                  {{ warning }}
                </li>
              </ul>
            </IAlertBody>
          </IAlert>

          <div class="grid gap-3 text-sm">
            <div>
              <ITextDark class="font-medium" text="Identity Checks" />
              <pre class="mt-1 max-h-40 overflow-auto whitespace-pre-wrap rounded-md bg-neutral-50 p-3 text-xs dark:bg-neutral-900">{{ formatJson(publishReadinessReport.identity_checks) }}</pre>
            </div>
            <div>
              <ITextDark class="font-medium" text="Existing App Conflicts" />
              <pre class="mt-1 max-h-40 overflow-auto whitespace-pre-wrap rounded-md bg-neutral-50 p-3 text-xs dark:bg-neutral-900">{{ formatJson(publishReadinessReport.existing_app_conflicts) }}</pre>
            </div>
            <div>
              <ITextDark class="font-medium" text="Field Impact" />
              <pre class="mt-1 max-h-40 overflow-auto whitespace-pre-wrap rounded-md bg-neutral-50 p-3 text-xs dark:bg-neutral-900">{{ formatJson(publishReadinessReport.field_impact) }}</pre>
            </div>
            <div>
              <ITextDark class="font-medium" text="Relation Impact" />
              <pre class="mt-1 max-h-40 overflow-auto whitespace-pre-wrap rounded-md bg-neutral-50 p-3 text-xs dark:bg-neutral-900">{{ formatJson(publishReadinessReport.relation_impact) }}</pre>
            </div>
            <div>
              <ITextDark class="font-medium" text="Form Layout Impact" />
              <pre class="mt-1 max-h-40 overflow-auto whitespace-pre-wrap rounded-md bg-neutral-50 p-3 text-xs dark:bg-neutral-900">{{ formatJson(publishReadinessReport.form_layout_impact) }}</pre>
            </div>
            <div>
              <ITextDark class="font-medium" text="Automation Impact" />
              <pre class="mt-1 max-h-40 overflow-auto whitespace-pre-wrap rounded-md bg-neutral-50 p-3 text-xs dark:bg-neutral-900">{{ formatJson(publishReadinessReport.automation_impact) }}</pre>
            </div>
            <div>
              <ITextDark class="font-medium" text="Conflicts" />
              <pre class="mt-1 max-h-40 overflow-auto whitespace-pre-wrap rounded-md bg-neutral-50 p-3 text-xs dark:bg-neutral-900">{{ formatJson(publishReadinessReport.conflicts) }}</pre>
            </div>
            <div>
              <ITextDark class="font-medium" text="File plan" />
              <pre class="mt-1 max-h-40 overflow-auto whitespace-pre-wrap rounded-md bg-neutral-50 p-3 text-xs dark:bg-neutral-900">{{ formatJson(publishReadinessReport.file_plan) }}</pre>
            </div>
            <div>
              <ITextDark class="font-medium" text="Database plan" />
              <pre class="mt-1 max-h-40 overflow-auto whitespace-pre-wrap rounded-md bg-neutral-50 p-3 text-xs dark:bg-neutral-900">{{ formatJson(publishReadinessReport.database_plan) }}</pre>
            </div>
            <div>
              <ITextDark class="font-medium" text="Capability impact" />
              <pre class="mt-1 max-h-40 overflow-auto whitespace-pre-wrap rounded-md bg-neutral-50 p-3 text-xs dark:bg-neutral-900">{{ formatJson(publishReadinessReport.capability_impact) }}</pre>
            </div>
            <div>
              <ITextDark class="font-medium" text="Rollback requirements" />
              <pre class="mt-1 max-h-40 overflow-auto whitespace-pre-wrap rounded-md bg-neutral-50 p-3 text-xs dark:bg-neutral-900">{{ formatJson(publishReadinessReport.rollback_requirements) }}</pre>
            </div>
          </div>
        </div>

        <pre class="max-h-96 overflow-auto whitespace-pre-wrap rounded-md bg-neutral-50 p-3 text-xs dark:bg-neutral-900">{{ formattedPublishReadinessReport }}</pre>
      </ICardBody>
    </ICard>

    <ICard>
      <ICardHeader>
        <ICardHeading text="Publish Dry Run" />
      </ICardHeader>

      <ICardBody class="space-y-3">
        <IAlert variant="info">
          <IAlertBody>
            Dry-run only. Files are generated under storage/app/builder-publish-dry-runs for review. No runtime modules, migrations, tables, routes, or publish actions are performed.
          </IAlertBody>
        </IAlert>

        <div v-if="publishDryRunReport" class="space-y-3">
          <div class="grid gap-3 text-sm">
            <div class="flex justify-between gap-4">
              <span class="text-neutral-500 dark:text-neutral-400">dry_run_root</span>
              <span class="break-all text-right font-mono text-xs">{{ publishDryRunReport.dry_run_root }}</span>
            </div>
            <div class="flex justify-between gap-4">
              <span class="text-neutral-500 dark:text-neutral-400">dry_run_artifacts_written</span>
              <span class="font-mono">{{ publishDryRunReport.dry_run_artifacts_written }}</span>
            </div>
            <div class="flex justify-between gap-4">
              <span class="text-neutral-500 dark:text-neutral-400">runtime_writes_performed</span>
              <span class="font-mono">{{ publishDryRunReport.runtime_writes_performed }}</span>
            </div>
            <div class="flex justify-between gap-4">
              <span class="text-neutral-500 dark:text-neutral-400">publish_executed</span>
              <span class="font-mono">{{ String(publishDryRunReport.publish_executed) }}</span>
            </div>
            <div class="flex justify-between gap-4">
              <span class="text-neutral-500 dark:text-neutral-400">manifest path</span>
              <span class="break-all text-right font-mono text-xs">{{ dryRunManifestPath }}</span>
            </div>
          </div>

          <IAlert v-if="publishDryRunReport.blockers?.length" variant="danger">
            <IAlertBody>
              <div class="mb-1 font-medium">Blockers</div>
              <ul class="list-disc space-y-1 pl-5">
                <li v-for="blocker in publishDryRunReport.blockers" :key="blocker">
                  {{ blocker }}
                </li>
              </ul>
            </IAlertBody>
          </IAlert>

          <IAlert v-if="publishDryRunReport.warnings?.length" variant="warning">
            <IAlertBody>
              <div class="mb-1 font-medium">Warnings</div>
              <ul class="list-disc space-y-1 pl-5">
                <li v-for="warning in publishDryRunReport.warnings" :key="warning">
                  {{ warning }}
                </li>
              </ul>
            </IAlertBody>
          </IAlert>

          <div>
            <ITextDark class="font-medium" text="Files" />
            <pre class="mt-1 max-h-56 overflow-auto whitespace-pre-wrap rounded-md bg-neutral-50 p-3 text-xs dark:bg-neutral-900">{{ formatJson(publishDryRunReport.files) }}</pre>
          </div>
        </div>

        <pre class="max-h-96 overflow-auto whitespace-pre-wrap rounded-md bg-neutral-50 p-3 text-xs dark:bg-neutral-900">{{ formattedPublishDryRunReport }}</pre>
      </ICardBody>
    </ICard>
  </div>
</template>

<script setup>
import { computed } from 'vue'

import BuilderStatusBadge from './BuilderStatusBadge.vue'

const props = defineProps({
  saving: Boolean,
  validating: Boolean,
  previewing: Boolean,
  readinessAnalyzing: Boolean,
  dryRunGenerating: Boolean,
  validationReport: Object,
  previewRun: Object,
  previewManifest: Object,
  publishReadinessReport: Object,
  publishDryRunReport: Object,
})

defineEmits(['save', 'validate', 'preview', 'analyze-readiness', 'generate-dry-run'])

const formattedValidationReport = computed(() => formatJson(props.validationReport))

const validationStatus = computed(() => {
  if (!props.validationReport) {
    return 'not_run'
  }

  return props.validationReport.valid ? 'validated' : 'validation_failed'
})

const previewStatus = computed(() => props.previewRun?.status || 'not_run')

const previewPath = computed(
  () => props.previewRun?.preview_path || props.previewManifest?.preview_path
)

const formattedPreviewOutput = computed(() => {
  if (props.previewRun?.output_text) {
    return props.previewRun.output_text
  }

  return formatJson(props.previewRun?.manifest_json || props.previewManifest)
})

const formattedPublishReadinessReport = computed(() =>
  formatJson(props.publishReadinessReport)
)

const formattedPublishDryRunReport = computed(() =>
  formatJson(props.publishDryRunReport)
)

const dryRunManifestPath = computed(() => {
  if (!props.publishDryRunReport?.dry_run_root) {
    return '-'
  }

  return `${props.publishDryRunReport.dry_run_root}/manifest/publish-dry-run-manifest.json`
})

function formatJson(value) {
  if (!value) {
    return 'Not run yet.'
  }

  return JSON.stringify(value, null, 2)
}
</script>
