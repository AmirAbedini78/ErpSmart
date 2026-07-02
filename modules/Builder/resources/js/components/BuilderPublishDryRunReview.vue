<template>
  <ICard>
    <ICardHeader>
      <ICardHeading text="Publish Dry Run Review" />
    </ICardHeader>

    <ICardBody class="space-y-3">
      <IAlert variant="info">
        <IAlertBody>
          Review only. Dry-run artifacts must not be copied into runtime paths. Publish is not available in this MVP.
        </IAlertBody>
      </IAlert>

      <IAlert variant="info">
        <IAlertBody>
          Dry-run only. Files are generated under storage/app/builder-publish-dry-runs for review. No runtime modules, migrations, tables, routes, or publish actions are performed.
        </IAlertBody>
      </IAlert>

      <div v-if="report" class="space-y-4">
        <div class="grid gap-3 text-sm">
          <div class="flex justify-between gap-4">
            <span class="text-neutral-500 dark:text-neutral-400">dry_run_root</span>
            <span class="break-all text-right font-mono text-xs">{{ report.dry_run_root }}</span>
          </div>
          <div class="flex justify-between gap-4">
            <span class="text-neutral-500 dark:text-neutral-400">manifest path</span>
            <span class="break-all text-right font-mono text-xs">{{ manifestPath }}</span>
          </div>
          <div class="flex justify-between gap-4">
            <span class="text-neutral-500 dark:text-neutral-400">artifacts count</span>
            <span class="font-mono">{{ report.dry_run_artifacts_written }}</span>
          </div>
          <div class="flex justify-between gap-4">
            <span class="text-neutral-500 dark:text-neutral-400">runtime_writes_performed</span>
            <span class="font-mono">{{ report.runtime_writes_performed }}</span>
          </div>
          <div class="flex justify-between gap-4">
            <span class="text-neutral-500 dark:text-neutral-400">publish_executed</span>
            <span class="font-mono">{{ String(report.publish_executed) }}</span>
          </div>
        </div>

        <div>
          <ITextDark class="font-medium" text="Artifact Summary" />
          <pre class="mt-1 max-h-52 overflow-auto whitespace-pre-wrap rounded-md bg-neutral-50 p-3 text-xs dark:bg-neutral-900">{{ formatJson(report.artifact_summary) }}</pre>
        </div>

        <div>
          <ITextDark class="font-medium" text="Generated Artifact List" />
          <div class="mt-2 overflow-auto rounded-md border border-neutral-200 dark:border-neutral-700">
            <table class="min-w-full text-left text-xs">
              <thead class="bg-neutral-50 dark:bg-neutral-900">
                <tr>
                  <th class="px-3 py-2">type</th>
                  <th class="px-3 py-2">future_runtime_path</th>
                  <th class="px-3 py-2">dry_run_path</th>
                  <th class="px-3 py-2">runtime_written</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="file in report.files" :key="file.dry_run_path">
                  <td class="px-3 py-2">{{ file.type }}</td>
                  <td class="px-3 py-2 font-mono">{{ file.future_runtime_path }}</td>
                  <td class="px-3 py-2 font-mono">{{ file.dry_run_path }}</td>
                  <td class="px-3 py-2 font-mono">{{ String(file.runtime_written) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div>
          <ITextDark class="font-medium" text="Safety Checklist" />
          <ul class="mt-2 space-y-2 text-sm">
            <li v-for="item in report.review?.safety_checklist || []" :key="item.key">
              <span class="font-medium">{{ item.label }}</span>
              <span class="font-mono text-xs"> - {{ item.status }}</span>
            </li>
          </ul>
        </div>

        <div>
          <ITextDark class="font-medium" text="Approval Checklist" />
          <ul class="mt-2 space-y-2 text-sm">
            <li v-for="item in report.review?.approval_checklist || []" :key="item.key">
              <span class="font-medium">{{ item.label }}</span>
              <span class="font-mono text-xs"> - {{ item.status }}</span>
              <span v-if="item.notes" class="text-neutral-500 dark:text-neutral-400"> - {{ item.notes }}</span>
            </li>
          </ul>
        </div>

        <div>
          <ITextDark class="font-medium" text="Next Allowed Actions" />
          <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
            <li v-for="action in report.review?.next_allowed_actions || []" :key="action">
              {{ action }}
            </li>
          </ul>
        </div>

        <div>
          <ITextDark class="font-medium" text="Forbidden Actions" />
          <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
            <li v-for="action in report.review?.forbidden_actions || []" :key="action">
              {{ action }}
            </li>
          </ul>
        </div>

        <pre class="max-h-96 overflow-auto whitespace-pre-wrap rounded-md bg-neutral-50 p-3 text-xs dark:bg-neutral-900">{{ formatJson(report) }}</pre>
      </div>

      <pre v-else class="max-h-96 overflow-auto whitespace-pre-wrap rounded-md bg-neutral-50 p-3 text-xs dark:bg-neutral-900">Not run yet.</pre>
    </ICardBody>
  </ICard>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  report: Object,
})

const manifestPath = computed(() => {
  if (!props.report?.dry_run_root) {
    return '-'
  }

  return `${props.report.dry_run_root}/manifest/publish-dry-run-manifest.json`
})

function formatJson(value) {
  if (!value) {
    return 'Not run yet.'
  }

  return JSON.stringify(value, null, 2)
}
</script>
