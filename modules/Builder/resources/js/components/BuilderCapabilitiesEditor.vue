<template>
  <ICard>
    <ICardHeader>
      <ICardHeading text="Capabilities" />
    </ICardHeader>

    <ICardBody class="space-y-5">
      <div v-for="group in groups" :key="group.name">
        <div class="mb-3 flex items-center gap-2">
          <ITextDark class="font-medium" :text="group.name" />
          <IBadge v-if="group.future" text="Preview warning only" variant="warning" />
        </div>

        <div class="grid gap-3 md:grid-cols-3">
          <IFormCheckboxField v-for="capability in group.items" :key="capability">
            <IFormCheckbox
              v-model:checked="definition.capabilities[capability]"
              @change="changed(capability)"
            />
            <IFormCheckboxLabel>
              {{ capability }}
              <span v-if="group.future" class="text-warning-600">
                (Preview warning only)
              </span>
            </IFormCheckboxLabel>
          </IFormCheckboxField>
        </div>
      </div>
    </ICardBody>
  </ICard>
</template>

<script setup>
const props = defineProps({
  definition: { type: Object, required: true },
})

const emit = defineEmits(['changed'])

const groups = [
  {
    name: 'Data/UI',
    items: [
      'tableable',
      'hasDetailView',
      'customFields',
      'uniqueCustomFields',
      'importable',
      'exportable',
      'cloneable',
      'bulkDelete',
      'globalSearch',
      'quickCreate',
      'floatingModal',
    ],
  },
  {
    name: 'Collaboration/Content',
    items: [
      'notes',
      'comments',
      'activities',
      'activityComments',
      'activityAssociations',
      'media',
    ],
  },
  {
    name: 'Future/Warning-only',
    future: true,
    items: [
      'documents',
      'calls',
      'emails',
      'emailSending',
      'tasks',
      'workflow',
      'approvals',
      'notifications',
      'timeline',
      'softDeletes',
    ],
  },
  {
    name: 'Form/Layout',
    future: true,
    items: ['formLayout', 'stepperForm', 'sections', 'conditionalVisibility'],
  },
]

function changed(capability) {
  if (capability === 'hasDetailView') {
    props.definition.resource.hasDetailView = Boolean(
      props.definition.capabilities.hasDetailView
    )
  }

  emit('changed')
}
</script>
