<template>
  <ICard>
    <ICardHeader>
      <ICardHeading text="Publish Candidate Snapshot" />
    </ICardHeader>

    <ICardBody class="space-y-3">
      <IAlert variant="info">
        <IAlertBody>
          Snapshot only. This freezes a review artifact under storage/app/builder-publish-candidates. It does not approve or execute publish.
        </IAlertBody>
      </IAlert>

      <div v-if="snapshot" class="space-y-4">
        <div class="grid gap-3 text-sm">
          <div class="flex justify-between gap-4">
            <span class="text-neutral-500 dark:text-neutral-400">candidate_id</span>
            <span class="break-all text-right font-mono text-xs">{{ snapshot.candidate_id }}</span>
          </div>
          <div class="flex justify-between gap-4">
            <span class="text-neutral-500 dark:text-neutral-400">candidate_root</span>
            <span class="break-all text-right font-mono text-xs">{{ snapshot.candidate_root }}</span>
          </div>
          <div class="flex justify-between gap-4">
            <span class="text-neutral-500 dark:text-neutral-400">candidate_snapshot_path</span>
            <span class="break-all text-right font-mono text-xs">{{ snapshot.candidate_snapshot_path }}</span>
          </div>
          <div class="flex justify-between gap-4">
            <span class="text-neutral-500 dark:text-neutral-400">candidate_status</span>
            <span class="font-mono">{{ snapshot.candidate_status }}</span>
          </div>
          <div class="flex justify-between gap-4">
            <span class="text-neutral-500 dark:text-neutral-400">candidate_artifacts_written</span>
            <span class="font-mono">{{ snapshot.candidate_artifacts_written }}</span>
          </div>
          <div class="flex justify-between gap-4">
            <span class="text-neutral-500 dark:text-neutral-400">runtime_writes_performed</span>
            <span class="font-mono">{{ snapshot.runtime_writes_performed }}</span>
          </div>
          <div class="flex justify-between gap-4">
            <span class="text-neutral-500 dark:text-neutral-400">publish_executed</span>
            <span class="font-mono">{{ String(snapshot.publish_executed) }}</span>
          </div>
          <div class="flex justify-between gap-4">
            <span class="text-neutral-500 dark:text-neutral-400">approval_requested</span>
            <span class="font-mono">{{ String(snapshot.approval_requested) }}</span>
          </div>
          <div class="flex justify-between gap-4">
            <span class="text-neutral-500 dark:text-neutral-400">approval_granted</span>
            <span class="font-mono">{{ String(snapshot.approval_granted) }}</span>
          </div>
        </div>

        <div>
          <ITextDark class="font-medium" text="Candidate Checklist" />
          <ul class="mt-2 space-y-2 text-sm">
            <li v-for="item in snapshot.candidate_checklist || []" :key="item.key">
              <span class="font-medium">{{ item.key }}</span>
              <span class="font-mono text-xs"> - {{ item.status }}</span>
            </li>
          </ul>
        </div>

        <div>
          <ITextDark class="font-medium" text="Next Allowed Actions" />
          <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
            <li v-for="action in snapshot.next_allowed_actions || []" :key="action">
              {{ action }}
            </li>
          </ul>
        </div>

        <div>
          <ITextDark class="font-medium" text="Forbidden Actions" />
          <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
            <li v-for="action in snapshot.forbidden_actions || []" :key="action">
              {{ action }}
            </li>
          </ul>
        </div>

        <pre class="max-h-96 overflow-auto whitespace-pre-wrap rounded-md bg-neutral-50 p-3 text-xs dark:bg-neutral-900">{{ formatJson(snapshot) }}</pre>
      </div>

      <pre v-else class="max-h-96 overflow-auto whitespace-pre-wrap rounded-md bg-neutral-50 p-3 text-xs dark:bg-neutral-900">Not created yet.</pre>
    </ICardBody>
  </ICard>
</template>

<script setup>
defineProps({
  snapshot: Object,
})

function formatJson(value) {
  if (!value) {
    return 'Not created yet.'
  }

  return JSON.stringify(value, null, 2)
}
</script>
