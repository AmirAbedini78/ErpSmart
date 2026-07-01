<template>
  <IBadge :text="status || 'draft'" :variant="variant" />
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  status: String,
})

const variant = computed(() => {
  if (['validated', 'previewed', 'published'].includes(props.status)) {
    return 'success'
  }

  if (['validation_failed', 'preview_failed', 'publish_failed'].includes(props.status)) {
    return 'danger'
  }

  return 'neutral'
})
</script>
