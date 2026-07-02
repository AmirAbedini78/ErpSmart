<template>
  <ICard>
    <ICardHeader>
      <ICardHeading text="Publish Execution Records" />
    </ICardHeader>

    <ICardBody class="space-y-4">
      <IAlert variant="info">
        <IAlertBody>
          Execution record only. This acquires lock, runs preflight, and writes staging/rollback manifests under storage. It does not publish or write runtime files.
        </IAlertBody>
      </IAlert>

      <IButton
        class="w-full justify-center"
        icon="DocumentText"
        text="Create Publish Execution Record"
        :loading="loading"
        @click="$emit('create-execution-record')"
      />

      <IAlert variant="info">
        <IAlertBody>
          Staged validation only. This checks storage artifacts and does not copy files to runtime, run migrations, register routes, or publish.
        </IAlertBody>
      </IAlert>

      <IButton
        class="w-full justify-center"
        icon="CheckCircle"
        text="Validate Staged Files"
        :disabled="!latestExecutionId"
        :loading="validationLoading"
        @click="$emit('validate-staged-files', latestExecutionId)"
      />

      <div v-if="latestReport" class="space-y-3">
        <div class="grid gap-2 text-sm">
          <div class="flex justify-between gap-4">
            <span class="text-neutral-500 dark:text-neutral-400">status</span>
            <span class="font-mono">{{ latestReport.status }}</span>
          </div>
          <div class="flex justify-between gap-4">
            <span class="text-neutral-500 dark:text-neutral-400">runtime_writes_performed</span>
            <span class="font-mono">{{ latestReport.runtime_writes_performed }}</span>
          </div>
          <div class="flex justify-between gap-4">
            <span class="text-neutral-500 dark:text-neutral-400">publish_executed</span>
            <span class="font-mono">{{ String(latestReport.publish_executed) }}</span>
          </div>
          <div class="flex justify-between gap-4">
            <span class="text-neutral-500 dark:text-neutral-400">lock</span>
            <span class="font-mono">acquired={{ String(latestReport.lock?.acquired) }}, released={{ String(latestReport.lock?.released) }}</span>
          </div>
          <div class="flex justify-between gap-4">
            <span class="text-neutral-500 dark:text-neutral-400">staging_root</span>
            <span class="break-all text-right font-mono text-xs">{{ latestReport.staging_root || '-' }}</span>
          </div>
          <div class="flex justify-between gap-4">
            <span class="text-neutral-500 dark:text-neutral-400">rollback_manifest_path</span>
            <span class="break-all text-right font-mono text-xs">{{ latestReport.rollback_manifest_path || '-' }}</span>
          </div>
        </div>

        <div v-if="latestReport.forbidden_actions?.length">
          <ITextDark class="font-medium" text="Forbidden actions" />
          <ul class="mt-1 list-disc space-y-1 pl-5 text-sm">
            <li v-for="action in latestReport.forbidden_actions" :key="action">
              {{ action }}
            </li>
          </ul>
        </div>

        <div v-if="latestReport.next_allowed_actions?.length">
          <ITextDark class="font-medium" text="Next allowed actions" />
          <ul class="mt-1 list-disc space-y-1 pl-5 text-sm">
            <li v-for="action in latestReport.next_allowed_actions" :key="action">
              {{ action }}
            </li>
          </ul>
        </div>

        <pre class="max-h-96 overflow-auto whitespace-pre-wrap rounded-md bg-neutral-50 p-3 text-xs dark:bg-neutral-900">{{ formattedLatestReport }}</pre>
      </div>

      <div v-if="validationReport" class="space-y-3">
        <ITextDark class="font-medium" text="Staged file validation" />
        <div class="grid gap-2 text-sm">
          <div class="flex justify-between gap-4">
            <span class="text-neutral-500 dark:text-neutral-400">status</span>
            <span class="font-mono">{{ validationReport.status }}</span>
          </div>
          <div class="flex justify-between gap-4">
            <span class="text-neutral-500 dark:text-neutral-400">safe</span>
            <span class="font-mono">{{ String(validationReport.safe) }}</span>
          </div>
          <div class="flex justify-between gap-4">
            <span class="text-neutral-500 dark:text-neutral-400">validation_report_path</span>
            <span class="break-all text-right font-mono text-xs">{{ validationReport.validation_report_path || '-' }}</span>
          </div>
          <div class="flex justify-between gap-4">
            <span class="text-neutral-500 dark:text-neutral-400">files</span>
            <span class="font-mono">{{ validationReport.summary?.total_files || 0 }}</span>
          </div>
        </div>

        <IAlert v-if="validationReport.blockers?.length" variant="danger">
          <IAlertBody>
            <div class="mb-1 font-medium">Blockers</div>
            <ul class="list-disc space-y-1 pl-5">
              <li v-for="blocker in validationReport.blockers" :key="blocker">
                {{ blocker }}
              </li>
            </ul>
          </IAlertBody>
        </IAlert>

        <IAlert v-if="validationReport.warnings?.length" variant="warning">
          <IAlertBody>
            <div class="mb-1 font-medium">Warnings</div>
            <ul class="list-disc space-y-1 pl-5">
              <li v-for="warning in validationReport.warnings" :key="warning">
                {{ warning }}
              </li>
            </ul>
          </IAlertBody>
        </IAlert>

        <div v-if="validationReport.checks?.length">
          <ITextDark class="font-medium" text="Checks" />
          <ul class="mt-1 space-y-1 text-sm">
            <li v-for="check in validationReport.checks" :key="check.key">
              <span class="font-mono">{{ check.status }}</span> {{ check.key }} - {{ check.message }}
            </li>
          </ul>
        </div>

        <div v-if="validationReport.forbidden_actions?.length">
          <ITextDark class="font-medium" text="Forbidden actions" />
          <ul class="mt-1 list-disc space-y-1 pl-5 text-sm">
            <li v-for="action in validationReport.forbidden_actions" :key="action">
              {{ action }}
            </li>
          </ul>
        </div>

        <pre class="max-h-96 overflow-auto whitespace-pre-wrap rounded-md bg-neutral-50 p-3 text-xs dark:bg-neutral-900">{{ formattedValidationReport }}</pre>
      </div>

      <div v-if="records.length" class="space-y-2">
        <ITextDark class="font-medium" text="Execution records" />
        <div
          v-for="record in records"
          :key="record.id"
          class="rounded-md border border-neutral-200 p-3 text-sm dark:border-neutral-700"
        >
          <div class="flex items-center justify-between gap-3">
            <span class="font-medium">#{{ record.id }} {{ record.status }}</span>
            <span class="text-xs text-neutral-500 dark:text-neutral-400">{{ record.created_at }}</span>
          </div>
          <div class="mt-2 grid gap-1 text-xs">
            <div>candidate_id: <span class="font-mono">{{ record.candidate_id || '-' }}</span></div>
            <div>staging_root: <span class="break-all font-mono">{{ record.staging_root || '-' }}</span></div>
            <div>rollback_manifest_path: <span class="break-all font-mono">{{ record.rollback_manifest_path || '-' }}</span></div>
          </div>
        </div>
      </div>
    </ICardBody>
  </ICard>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  records: {
    type: Array,
    default: () => [],
  },
  latestReport: Object,
  validationReport: Object,
  loading: Boolean,
  validationLoading: Boolean,
})

defineEmits(['create-execution-record', 'validate-staged-files'])

const latestExecutionId = computed(() =>
  props.latestReport?.execution_id || props.records?.[0]?.id || null
)

const formattedLatestReport = computed(() =>
  props.latestReport ? JSON.stringify(props.latestReport, null, 2) : 'Not run yet.'
)

const formattedValidationReport = computed(() =>
  props.validationReport ? JSON.stringify(props.validationReport, null, 2) : 'Not run yet.'
)
</script>
