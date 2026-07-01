<template>
  <ICard>
    <ICardHeader>
      <ICardHeading text="Builder Summary" />
    </ICardHeader>

    <ICardBody class="space-y-4">
      <div class="space-y-3 text-sm">
        <div class="flex justify-between gap-4">
          <span class="text-neutral-500 dark:text-neutral-400">Module</span>
          <span class="font-medium">{{ definitionJson.module?.name || '-' }}</span>
        </div>
        <div class="flex justify-between gap-4">
          <span class="text-neutral-500 dark:text-neutral-400">Entity</span>
          <span class="font-medium">{{ definitionJson.module?.singularLabel || '-' }}</span>
        </div>
        <div class="flex justify-between gap-4">
          <span class="text-neutral-500 dark:text-neutral-400">Resource</span>
          <span class="font-medium">{{ definitionJson.module?.resourceName || '-' }}</span>
        </div>
        <div class="flex justify-between gap-4">
          <span class="text-neutral-500 dark:text-neutral-400">Field count</span>
          <span class="font-medium">{{ fieldCount }}</span>
        </div>
        <div class="flex justify-between gap-4">
          <span class="text-neutral-500 dark:text-neutral-400">Relation count</span>
          <span class="font-medium">{{ relationCount }}</span>
        </div>
        <div class="flex justify-between gap-4">
          <span class="text-neutral-500 dark:text-neutral-400">Capability count</span>
          <span class="font-medium">{{ enabledCapabilityCount }}</span>
        </div>
        <div class="flex justify-between gap-4">
          <span class="text-neutral-500 dark:text-neutral-400">Form Layout</span>
          <span class="font-medium">{{ definitionJson.formLayout?.enabled ? 'enabled' : 'metadata off' }}</span>
        </div>
        <div class="flex justify-between gap-4">
          <span class="text-neutral-500 dark:text-neutral-400">Automation</span>
          <span class="font-medium">{{ definitionJson.automation?.enabled ? 'enabled' : 'metadata off' }}</span>
        </div>
      </div>

      <div class="flex flex-wrap gap-2">
        <IBadge text="Preview-only MVP" variant="warning" />
        <IBadge text="No publish" variant="neutral" />
        <IBadge text="No runtime writes" variant="neutral" />
      </div>

      <BuilderStatusBadge :status="status" />
    </ICardBody>
  </ICard>
</template>

<script setup>
import { computed } from 'vue'

import BuilderStatusBadge from './BuilderStatusBadge.vue'

const props = defineProps({
  definitionJson: { type: Object, required: true },
  status: String,
})

const fieldCount = computed(() => props.definitionJson.fields?.length || 0)
const relationCount = computed(() => props.definitionJson.relations?.length || 0)
const enabledCapabilityCount = computed(() =>
  Object.values(props.definitionJson.capabilities || {}).filter(Boolean).length
)
</script>
