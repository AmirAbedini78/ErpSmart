<template>
  <ICard>
    <ICardHeader>
      <ICardHeading text="Approved Candidate Preflight" />
    </ICardHeader>

    <ICardBody class="space-y-3">
      <IAlert variant="info">
        <IAlertBody>
          Preflight only. This checks approval and candidate freshness. It does not publish or write runtime files.
        </IAlertBody>
      </IAlert>

      <IButton
        class="w-full justify-center"
        icon="CheckCircle"
        text="Check Approved Candidate Preflight"
        :loading="loading"
        @click="$emit('check-preflight')"
      />

      <div v-if="report" class="space-y-4">
        <div class="grid gap-3 text-sm">
          <div class="flex justify-between gap-4">
            <span class="text-neutral-500 dark:text-neutral-400">eligible_for_future_publish</span>
            <span class="font-mono">{{ String(report.eligible_for_future_publish) }}</span>
          </div>
          <div class="flex justify-between gap-4">
            <span class="text-neutral-500 dark:text-neutral-400">status</span>
            <span class="font-mono">{{ report.status }}</span>
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

        <IAlert v-if="report.blockers?.length" variant="danger">
          <IAlertBody>
            <div class="mb-1 font-medium">Blockers</div>
            <ul class="list-disc space-y-1 pl-5">
              <li v-for="blocker in report.blockers" :key="blocker">
                {{ blocker }}
              </li>
            </ul>
          </IAlertBody>
        </IAlert>

        <IAlert v-if="report.warnings?.length" variant="warning">
          <IAlertBody>
            <div class="mb-1 font-medium">Warnings</div>
            <ul class="list-disc space-y-1 pl-5">
              <li v-for="warning in report.warnings" :key="warning">
                {{ warning }}
              </li>
            </ul>
          </IAlertBody>
        </IAlert>

        <div>
          <ITextDark class="font-medium" text="Approval Request" />
          <pre class="mt-1 max-h-48 overflow-auto whitespace-pre-wrap rounded-md bg-neutral-50 p-3 text-xs dark:bg-neutral-900">{{ formatJson(report.approval_request) }}</pre>
        </div>

        <div>
          <ITextDark class="font-medium" text="Checks" />
          <ul class="mt-2 space-y-2 text-sm">
            <li v-for="check in report.checks || []" :key="check.key">
              <span class="font-medium">{{ check.key }}</span>
              <span class="font-mono text-xs"> - {{ check.status }}</span>
              <span class="text-neutral-500 dark:text-neutral-400"> - {{ check.message }}</span>
            </li>
          </ul>
        </div>

        <div>
          <ITextDark class="font-medium" text="Forbidden Actions" />
          <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
            <li v-for="action in report.forbidden_actions || []" :key="action">
              {{ action }}
            </li>
          </ul>
        </div>

        <div>
          <ITextDark class="font-medium" text="Next Allowed Actions" />
          <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
            <li v-for="action in report.next_allowed_actions || []" :key="action">
              {{ action }}
            </li>
          </ul>
        </div>

        <pre class="max-h-96 overflow-auto whitespace-pre-wrap rounded-md bg-neutral-50 p-3 text-xs dark:bg-neutral-900">{{ formatJson(report) }}</pre>
      </div>

      <pre v-else class="max-h-96 overflow-auto whitespace-pre-wrap rounded-md bg-neutral-50 p-3 text-xs dark:bg-neutral-900">Not checked yet.</pre>
    </ICardBody>
  </ICard>
</template>

<script setup>
defineProps({
  report: Object,
  loading: Boolean,
})

defineEmits(['check-preflight'])

function formatJson(value) {
  if (!value) {
    return 'Not checked yet.'
  }

  return JSON.stringify(value, null, 2)
}
</script>
