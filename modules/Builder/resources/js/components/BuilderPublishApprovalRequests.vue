<template>
  <ICard>
    <ICardHeader>
      <ICardHeading text="Human Approval Requests" />
    </ICardHeader>

    <ICardBody class="space-y-3">
      <IAlert variant="info">
        <IAlertBody>
          Approval does not publish. It only records human review state for a candidate snapshot.
        </IAlertBody>
      </IAlert>

      <IButton
        class="w-full justify-center"
        icon="CheckCircle"
        text="Request Approval"
        :loading="loading"
        @click="$emit('request-approval')"
      />

      <div v-if="requests?.length" class="space-y-3">
        <div
          v-for="request in requests"
          :key="request.id"
          class="rounded-md border border-neutral-200 p-3 text-sm dark:border-neutral-700"
        >
          <div class="mb-2 flex items-center justify-between gap-3">
            <span class="font-medium">#{{ request.id }} {{ request.status }}</span>
            <span class="font-mono text-xs">{{ request.candidate_id }}</span>
          </div>

          <div class="grid gap-2 text-xs">
            <div class="flex justify-between gap-3">
              <span class="text-neutral-500 dark:text-neutral-400">checksum</span>
              <span class="max-w-44 truncate font-mono">{{ request.definition_checksum || '-' }}</span>
            </div>
            <div class="flex justify-between gap-3">
              <span class="text-neutral-500 dark:text-neutral-400">candidate_snapshot_path</span>
              <span class="max-w-44 truncate font-mono">{{ request.candidate_snapshot_path }}</span>
            </div>
          </div>

          <div v-if="request.audit_logs?.length" class="mt-3">
            <ITextDark class="font-medium" text="Audit events" />
            <ul class="mt-1 list-disc space-y-1 pl-5 text-xs">
              <li v-for="event in request.audit_logs" :key="event.id">
                {{ event.event_type }}
              </li>
            </ul>
          </div>

          <div class="mt-3 grid gap-2 sm:grid-cols-3">
            <IButton
              size="sm"
              basic
              text="Approve Candidate"
              :disabled="request.status !== 'requested'"
              @click="$emit('approve-candidate', request)"
            />
            <IButton
              size="sm"
              basic
              text="Reject Candidate"
              :disabled="request.status !== 'requested'"
              @click="$emit('reject-candidate', request)"
            />
            <IButton
              size="sm"
              basic
              text="Revoke Approval"
              :disabled="!['requested', 'approved'].includes(request.status)"
              @click="$emit('revoke-approval', request)"
            />
          </div>
        </div>
      </div>

      <pre v-else class="max-h-96 overflow-auto whitespace-pre-wrap rounded-md bg-neutral-50 p-3 text-xs dark:bg-neutral-900">No approval requests yet.</pre>
    </ICardBody>
  </ICard>
</template>

<script setup>
defineProps({
  requests: Array,
  loading: Boolean,
})

defineEmits(['request-approval', 'approve-candidate', 'reject-candidate', 'revoke-approval'])
</script>
