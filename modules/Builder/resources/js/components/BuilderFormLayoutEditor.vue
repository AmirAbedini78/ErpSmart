<template>
  <ICard>
    <ICardHeader>
      <ICardHeading text="Form Layout" />
    </ICardHeader>

    <ICardBody class="space-y-5">
      <IAlert variant="warning">
        <IAlertBody>
          Form layout metadata only; runtime renderer not implemented yet.
        </IAlertBody>
      </IAlert>

      <div class="grid gap-4 md:grid-cols-3">
        <IFormCheckboxField>
          <IFormCheckbox
            v-model:checked="layout.enabled"
            @change="changed"
          />
          <IFormCheckboxLabel text="Enable form layout metadata" />
        </IFormCheckboxField>

        <IFormGroup label="Mode">
          <IFormSelect v-model="layout.mode" @change="modeChanged">
            <option value="standard">standard</option>
            <option value="stepper">stepper</option>
          </IFormSelect>
        </IFormGroup>
      </div>

      <div>
        <div class="mb-3 flex items-center justify-between gap-3">
          <ITextDark class="font-medium" text="Sections" />
          <IButton basic icon="PlusSolid" text="Add section" @click="addSection" />
        </div>

        <div class="space-y-4">
          <div
            v-for="(section, sectionIndex) in orderedSections"
            :key="section.id || sectionIndex"
            class="rounded-lg border border-neutral-200 p-4 dark:border-neutral-700"
          >
            <div class="mb-4 flex items-center justify-between gap-3">
              <div>
                <ITextDark class="font-medium" :text="section.label || section.id" />
                <IText class="text-sm" :text="`Order ${section.order || sectionIndex + 1}`" />
              </div>

              <div class="flex gap-2">
                <IButton basic text="Up" @click="moveSection(sectionIndex, -1)" />
                <IButton basic text="Down" @click="moveSection(sectionIndex, 1)" />
                <IButton basic icon="Trash" text="Remove" @click="removeSection(sectionIndex)" />
              </div>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
              <IFormGroup label="ID">
                <IFormInput v-model="section.id" @input="changed" />
              </IFormGroup>

              <IFormGroup label="Label">
                <IFormInput v-model="section.label" @input="changed" />
              </IFormGroup>

              <IFormGroup label="Columns">
                <IFormSelect v-model.number="section.columns" @change="changed">
                  <option :value="1">1</option>
                  <option :value="2">2</option>
                  <option :value="3">3</option>
                </IFormSelect>
              </IFormGroup>

              <IFormGroup label="Description" class="md:col-span-3">
                <IFormTextarea v-model="section.description" rows="2" @input="changed" />
              </IFormGroup>
            </div>

            <div class="mt-4 grid gap-3 md:grid-cols-3">
              <IFormCheckboxField v-for="mode in modes" :key="mode">
                <IFormCheckbox
                  :checked="section.modes.includes(mode)"
                  @change="toggleArrayValue(section.modes, mode)"
                />
                <IFormCheckboxLabel :text="`Show on ${mode}`" />
              </IFormCheckboxField>
            </div>

            <div class="mt-5">
              <div class="mb-3 flex items-center justify-between gap-3">
                <ITextDark class="font-medium" text="Section fields" />
                <div class="flex gap-2">
                  <IFormSelect v-model="selectedFields[section.id]">
                    <option value="">Select field</option>
                    <option
                      v-for="field in availableFields(section)"
                      :key="field.name"
                      :value="field.name"
                    >
                      {{ field.label || field.name }}
                    </option>
                  </IFormSelect>
                  <IButton basic text="Add field" @click="addFieldToSection(section)" />
                </div>
              </div>

              <div class="space-y-3">
                <div
                  v-for="(fieldLayout, fieldIndex) in section.fields"
                  :key="`${section.id}-${fieldLayout.field}-${fieldIndex}`"
                  class="rounded-md bg-neutral-50 p-3 dark:bg-neutral-900"
                >
                  <div class="mb-3 flex items-center justify-between gap-3">
                    <ITextDark class="font-medium" :text="fieldLayout.field" />
                    <div class="flex gap-2">
                      <IButton basic text="Up" @click="moveField(section, fieldIndex, -1)" />
                      <IButton basic text="Down" @click="moveField(section, fieldIndex, 1)" />
                      <IButton basic icon="Trash" text="Remove" @click="removeFieldFromSection(section, fieldIndex)" />
                    </div>
                  </div>

                  <div class="grid gap-4 md:grid-cols-3">
                    <IFormGroup label="Width">
                      <IFormSelect v-model="fieldLayout.width" @change="changed">
                        <option value="full">full</option>
                        <option value="1/2">1/2</option>
                        <option value="1/3">1/3</option>
                      </IFormSelect>
                    </IFormGroup>

                    <IFormGroup label="Required override">
                      <IFormSelect
                        :model-value="requiredOverrideValue(fieldLayout.requiredOverride)"
                        @change="fieldLayout.requiredOverride = parseNullableBoolean($event.target.value); changed()"
                      >
                        <option value="null">inherit</option>
                        <option value="true">required</option>
                        <option value="false">not required</option>
                      </IFormSelect>
                    </IFormGroup>

                    <IFormGroup label="Help text">
                      <IFormInput v-model="fieldLayout.helpText" @input="changed" />
                    </IFormGroup>
                  </div>

                  <div class="mt-3 grid gap-3 md:grid-cols-2">
                    <div>
                      <IText class="mb-2 text-sm" text="Readonly on" />
                      <div class="grid gap-2 md:grid-cols-3">
                        <IFormCheckboxField v-for="mode in modes" :key="`readonly-${mode}`">
                          <IFormCheckbox
                            :checked="fieldLayout.readonlyOn.includes(mode)"
                            @change="toggleArrayValue(fieldLayout.readonlyOn, mode)"
                          />
                          <IFormCheckboxLabel :text="mode" />
                        </IFormCheckboxField>
                      </div>
                    </div>

                    <div>
                      <IText class="mb-2 text-sm" text="Hidden on" />
                      <div class="grid gap-2 md:grid-cols-3">
                        <IFormCheckboxField v-for="mode in modes" :key="`hidden-${mode}`">
                          <IFormCheckbox
                            :checked="fieldLayout.hiddenOn.includes(mode)"
                            @change="toggleArrayValue(fieldLayout.hiddenOn, mode)"
                          />
                          <IFormCheckboxLabel :text="mode" />
                        </IFormCheckboxField>
                      </div>
                    </div>
                  </div>
                </div>

                <IText
                  v-if="section.fields.length === 0"
                  text="No fields assigned to this section."
                />
              </div>
            </div>
          </div>

          <IText v-if="layout.sections.length === 0" text="No form sections yet." />
        </div>
      </div>

      <div v-if="layout.mode === 'stepper'" class="space-y-4">
        <IAlert variant="warning">
          <IAlertBody>
            Stepper metadata only; runtime renderer not implemented yet.
          </IAlertBody>
        </IAlert>

        <div class="flex items-center justify-between gap-3">
          <ITextDark class="font-medium" text="Stepper" />
          <IButton basic icon="PlusSolid" text="Add step" @click="addStep" />
        </div>

        <IFormCheckboxField>
          <IFormCheckbox
            v-model:checked="layout.stepper.enabled"
            @change="changed"
          />
          <IFormCheckboxLabel text="Enable stepper metadata" />
        </IFormCheckboxField>

        <div
          v-for="(step, stepIndex) in layout.stepper.steps"
          :key="step.id || stepIndex"
          class="rounded-lg border border-neutral-200 p-4 dark:border-neutral-700"
        >
          <div class="mb-4 flex items-center justify-between gap-3">
            <ITextDark class="font-medium" :text="step.label || step.id" />
            <div class="flex gap-2">
              <IButton basic text="Up" @click="moveStep(stepIndex, -1)" />
              <IButton basic text="Down" @click="moveStep(stepIndex, 1)" />
              <IButton basic icon="Trash" text="Remove" @click="removeStep(stepIndex)" />
            </div>
          </div>

          <div class="grid gap-4 md:grid-cols-3">
            <IFormGroup label="Step ID">
              <IFormInput v-model="step.id" @input="changed" />
            </IFormGroup>

            <IFormGroup label="Label">
              <IFormInput v-model="step.label" @input="changed" />
            </IFormGroup>

            <IFormGroup label="Order">
              <IFormInput v-model.number="step.order" type="number" @input="changed" />
            </IFormGroup>
          </div>

          <div class="mt-4 grid gap-2 md:grid-cols-3">
            <IFormCheckboxField v-for="section in layout.sections" :key="section.id">
              <IFormCheckbox
                :checked="step.sectionIds.includes(section.id)"
                @change="toggleArrayValue(step.sectionIds, section.id)"
              />
              <IFormCheckboxLabel :text="section.label || section.id" />
            </IFormCheckboxField>
          </div>
        </div>
      </div>

      <div class="space-y-4">
        <IAlert variant="warning">
          <IAlertBody>
            Conditional visibility metadata only; runtime renderer not implemented yet.
          </IAlertBody>
        </IAlert>

        <div class="flex items-center justify-between gap-3">
          <ITextDark class="font-medium" text="Conditions" />
          <IButton basic icon="PlusSolid" text="Add condition" @click="addCondition" />
        </div>

        <div
          v-for="(condition, conditionIndex) in layout.conditions"
          :key="condition.id || conditionIndex"
          class="rounded-lg border border-neutral-200 p-4 dark:border-neutral-700"
        >
          <div class="mb-4 flex items-center justify-between gap-3">
            <ITextDark class="font-medium" :text="condition.id" />
            <IButton basic icon="Trash" text="Remove" @click="removeCondition(conditionIndex)" />
          </div>

          <div class="grid gap-4 md:grid-cols-3">
            <IFormGroup label="Condition ID">
              <IFormInput v-model="condition.id" @input="changed" />
            </IFormGroup>

            <IFormGroup label="Target field">
              <IFormSelect v-model="condition.targetField" @change="changed">
                <option value="">Select field</option>
                <option v-for="field in fieldOptions" :key="field.name" :value="field.name">
                  {{ field.label || field.name }}
                </option>
              </IFormSelect>
            </IFormGroup>

            <IFormGroup label="Operator">
              <IFormSelect v-model="condition.operator" @change="changed">
                <option value="equals">equals</option>
                <option value="not_equals">not_equals</option>
                <option value="empty">empty</option>
                <option value="not_empty">not_empty</option>
              </IFormSelect>
            </IFormGroup>

            <IFormGroup label="Value">
              <IFormInput v-model="condition.value" @input="changed" />
            </IFormGroup>

            <IFormGroup label="Effect">
              <IFormSelect v-model="condition.effect" @change="changed">
                <option value="show">show</option>
                <option value="hide">hide</option>
                <option value="require">require</option>
                <option value="readonly">readonly</option>
              </IFormSelect>
            </IFormGroup>
          </div>

          <div class="mt-4 grid gap-3 md:grid-cols-3">
            <IFormCheckboxField v-for="mode in modes" :key="`condition-${mode}`">
              <IFormCheckbox
                :checked="condition.appliesTo.includes(mode)"
                @change="toggleArrayValue(condition.appliesTo, mode)"
              />
              <IFormCheckboxLabel :text="`Applies to ${mode}`" />
            </IFormCheckboxField>
          </div>
        </div>

        <IText v-if="layout.conditions.length === 0" text="No conditions declared." />
      </div>
    </ICardBody>
  </ICard>
</template>

<script setup>
import { computed, reactive } from 'vue'

const props = defineProps({
  definition: { type: Object, required: true },
})

const emit = defineEmits(['changed'])

const modes = ['create', 'update', 'detail']
const selectedFields = reactive({})

const layout = computed(() => props.definition.formLayout)
const orderedSections = computed(() => layout.value.sections)
const fieldOptions = computed(() =>
  props.definition.fields.filter(field => field.name && field.name !== 'id')
)

function addSection() {
  const index = layout.value.sections.length + 1

  layout.value.enabled = true
  layout.value.sections.push({
    id: `section_${index}`,
    label: `Section ${index}`,
    description: '',
    order: index,
    modes: ['create', 'update', 'detail'],
    columns: 1,
    fields: [],
  })

  changed()
}

function removeSection(index) {
  const [section] = layout.value.sections.splice(index, 1)

  if (section?.id) {
    layout.value.stepper.steps.forEach(step => {
      step.sectionIds = step.sectionIds.filter(id => id !== section.id)
    })
  }

  reorder(layout.value.sections)
  changed()
}

function moveSection(index, direction) {
  moveItem(layout.value.sections, index, direction)
}

function availableFields(section) {
  const assigned = section.fields.map(field => field.field)

  return fieldOptions.value.filter(field => !assigned.includes(field.name))
}

function addFieldToSection(section) {
  const field = selectedFields[section.id]

  if (!field) {
    return
  }

  section.fields.push({
    field,
    order: section.fields.length + 1,
    width: 'full',
    requiredOverride: null,
    readonlyOn: ['detail'],
    hiddenOn: [],
    helpText: '',
  })

  selectedFields[section.id] = ''
  changed()
}

function removeFieldFromSection(section, index) {
  section.fields.splice(index, 1)
  reorder(section.fields)
  changed()
}

function moveField(section, index, direction) {
  moveItem(section.fields, index, direction)
}

function modeChanged() {
  layout.value.stepper.enabled = layout.value.mode === 'stepper'
  props.definition.capabilities.formLayout = Boolean(layout.value.enabled)
  props.definition.capabilities.stepperForm = layout.value.mode === 'stepper'
  changed()
}

function addStep() {
  const index = layout.value.stepper.steps.length + 1

  layout.value.mode = 'stepper'
  layout.value.stepper.enabled = true
  layout.value.stepper.steps.push({
    id: `step_${index}`,
    label: `Step ${index}`,
    sectionIds: [],
    order: index,
  })

  props.definition.capabilities.stepperForm = true
  changed()
}

function removeStep(index) {
  layout.value.stepper.steps.splice(index, 1)
  reorder(layout.value.stepper.steps)
  changed()
}

function moveStep(index, direction) {
  moveItem(layout.value.stepper.steps, index, direction)
}

function addCondition() {
  const index = layout.value.conditions.length + 1

  layout.value.conditions.push({
    id: `condition_${index}`,
    targetField: fieldOptions.value[0]?.name || '',
    operator: 'equals',
    value: '',
    effect: 'show',
    appliesTo: ['create', 'update'],
  })

  props.definition.capabilities.conditionalVisibility = true
  changed()
}

function removeCondition(index) {
  layout.value.conditions.splice(index, 1)
  changed()
}

function toggleArrayValue(array, value) {
  const index = array.indexOf(value)

  if (index === -1) {
    array.push(value)
  } else {
    array.splice(index, 1)
  }

  changed()
}

function moveItem(items, index, direction) {
  const nextIndex = index + direction

  if (nextIndex < 0 || nextIndex >= items.length) {
    return
  }

  const [item] = items.splice(index, 1)
  items.splice(nextIndex, 0, item)
  reorder(items)
  changed()
}

function reorder(items) {
  items.forEach((item, index) => {
    item.order = index + 1
  })
}

function requiredOverrideValue(value) {
  if (value === true) {
    return 'true'
  }

  if (value === false) {
    return 'false'
  }

  return 'null'
}

function parseNullableBoolean(value) {
  if (value === 'true') {
    return true
  }

  if (value === 'false') {
    return false
  }

  return null
}

function changed() {
  layout.value.sections.forEach(section => {
    section.fields ||= []
    section.modes ||= ['create', 'update', 'detail']
    section.fields.forEach(field => {
      field.readonlyOn ||= []
      field.hiddenOn ||= []
    })
  })

  layout.value.stepper ||= { enabled: false, steps: [] }
  layout.value.stepper.steps.forEach(step => {
    step.sectionIds ||= []
  })
  layout.value.conditions ||= []

  props.definition.capabilities.formLayout = Boolean(layout.value.enabled)
  props.definition.capabilities.sections = layout.value.sections.length > 0
  props.definition.capabilities.stepperForm = layout.value.mode === 'stepper'
  props.definition.capabilities.conditionalVisibility = layout.value.conditions.length > 0

  emit('changed')
}
</script>
