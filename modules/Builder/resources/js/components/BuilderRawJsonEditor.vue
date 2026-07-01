<template>
  <ICard>
    <ICardHeader>
      <ICardHeading text="Raw JSON" />
      <ICardActions>
        <IButton basic text="Format JSON" @click="$emit('format')" />
        <IButton basic text="Apply Raw JSON" @click="$emit('apply')" />
      </ICardActions>
    </ICardHeader>

    <ICardBody>
      <IFormTextarea
        :model-value="modelValue"
        rows="22"
        class="font-mono text-sm"
        @update:model-value="$emit('update:modelValue', $event)"
      />

      <IAlert v-if="error" class="mt-3" variant="danger">
        <IAlertBody>{{ error }}</IAlertBody>
      </IAlert>
    </ICardBody>
  </ICard>
</template>

<script setup>
defineProps({
  modelValue: { type: String, required: true },
  error: String,
})

defineEmits(['update:modelValue', 'apply', 'format'])
</script>
