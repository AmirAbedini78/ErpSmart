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
  </div>
</template>

<script setup>
import { computed } from 'vue'

import BuilderStatusBadge from './BuilderStatusBadge.vue'

const props = defineProps({
  saving: Boolean,
  validating: Boolean,
  previewing: Boolean,
  validationReport: Object,
  previewRun: Object,
  previewManifest: Object,
})

defineEmits(['save', 'validate', 'preview'])

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

function formatJson(value) {
  if (!value) {
    return 'Not run yet.'
  }

  return JSON.stringify(value, null, 2)
}
</script>
