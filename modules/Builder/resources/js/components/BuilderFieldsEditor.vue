<template>
  <ICard>
    <ICardHeader>
      <ICardHeading text="Fields" />
      <ICardActions>
        <IButton basic icon="PlusSolid" text="Add field" @click="addField" />
      </ICardActions>
    </ICardHeader>

    <ICardBody class="space-y-4">
      <div
        v-for="(field, index) in definition.fields"
        :key="field._builderKey || field.name || index"
        class="rounded-lg border border-neutral-200 p-4 dark:border-neutral-700"
      >
        <div class="mb-4 flex items-center justify-between gap-3">
          <div>
            <ITextDark class="font-medium" :text="field.label || field.name" />
            <IText class="text-sm" :text="field.type" />
          </div>

          <div class="flex gap-2">
            <IButton basic text="Duplicate" @click="duplicateField(index)" />
            <IButton
              basic
              icon="Trash"
              text="Remove"
              :disabled="field.name === 'id'"
              @click="removeField(index)"
            />
          </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
          <IFormGroup label="Name">
            <IFormInput v-model="field.name" @input="changed" />
          </IFormGroup>

          <IFormGroup label="Type">
            <IFormSelect v-model="field.type" @change="changed">
              <option v-for="type in fieldTypes" :key="type" :value="type">
                {{ type }}
              </option>
            </IFormSelect>
          </IFormGroup>

          <IFormGroup label="Label">
            <IFormInput v-model="field.label" @input="changed" />
          </IFormGroup>

          <IFormGroup label="Default">
            <IFormInput v-model="field.default" @input="changed" />
          </IFormGroup>

          <IFormGroup label="Table width">
            <IFormInput v-model="field.table.width" @input="changed" />
          </IFormGroup>

          <IFormGroup label="Table min width">
            <IFormInput v-model="field.table.minWidth" @input="changed" />
          </IFormGroup>
        </div>

        <div class="mt-4 grid gap-4 md:grid-cols-3">
          <IFormGroup label="Rules">
            <IFormTextarea
              :model-value="rulesToText(field.rules)"
              rows="3"
              @update:model-value="field.rules = textToRules($event); changed()"
            />
          </IFormGroup>

          <IFormGroup label="Creation rules">
            <IFormTextarea
              :model-value="rulesToText(field.creationRules)"
              rows="3"
              @update:model-value="field.creationRules = textToRules($event); changed()"
            />
          </IFormGroup>

          <IFormGroup label="Update rules">
            <IFormTextarea
              :model-value="rulesToText(field.updateRules)"
              rows="3"
              @update:model-value="field.updateRules = textToRules($event); changed()"
            />
          </IFormGroup>
        </div>

        <div class="mt-4 grid gap-3 md:grid-cols-4">
          <IFormCheckboxField>
            <IFormCheckbox v-model:checked="field.required" @change="changed" />
            <IFormCheckboxLabel text="Required" />
          </IFormCheckboxField>

          <IFormCheckboxField>
            <IFormCheckbox v-model:checked="field.primary" @change="changed" />
            <IFormCheckboxLabel text="Primary" />
          </IFormCheckboxField>

          <IFormCheckboxField>
            <IFormCheckbox
              v-model:checked="field.table.primary"
              @change="changed"
            />
            <IFormCheckboxLabel text="Table primary" />
          </IFormCheckboxField>
        </div>

        <div class="mt-4">
          <IFormGroup label="Table route">
            <IFormInput v-model="field.table.route" @input="changed" />
          </IFormGroup>
        </div>

        <div class="mt-4 grid gap-3 md:grid-cols-5">
          <IFormCheckboxField v-for="key in visibilityKeys" :key="key">
            <IFormCheckbox
              v-model:checked="field.visibility[key]"
              @change="changed"
            />
            <IFormCheckboxLabel :text="`Visible ${key}`" />
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

const fieldTypes = [
  'id',
  'text',
  'textarea',
  'boolean',
  'integer',
  'decimal',
  'date',
  'datetime',
  'select',
  'belongsTo',
]

const visibilityKeys = ['index', 'detail', 'create', 'update', 'settings']

function addField() {
  const index = props.definition.fields.length + 1
  props.definition.fields.push(normalizeField({
    name: `field_${index}`,
    type: 'text',
    label: `Field ${index}`,
    required: false,
    primary: false,
    rules: ['nullable', 'string'],
    visibility: {
      index: true,
      detail: true,
      create: true,
      update: true,
      settings: true,
    },
  }))
  changed()
}

function duplicateField(index) {
  const copy = JSON.parse(JSON.stringify(props.definition.fields[index]))
  copy.name = nextFieldName(copy.name || 'field')
  copy.label = `${copy.label || copy.name} Copy`
  props.definition.fields.splice(index + 1, 0, normalizeField(copy))
  changed()
}

function removeField(index) {
  if (props.definition.fields[index]?.name === 'id') {
    return
  }

  props.definition.fields.splice(index, 1)
  changed()
}

function normalizeField(field) {
  field.visibility ||= {}
  visibilityKeys.forEach(key => {
    if (typeof field.visibility[key] !== 'boolean') {
      field.visibility[key] = key !== 'settings' ? true : true
    }
  })
  field.rules ||= []
  field.creationRules ||= []
  field.updateRules ||= []
  field.table ||= {}

  return field
}

function rulesToText(rules) {
  return Array.isArray(rules) ? rules.join('\n') : ''
}

function textToRules(value) {
  return String(value || '')
    .split(/[\n,]/)
    .map(rule => rule.trim())
    .filter(Boolean)
}

function nextFieldName(base) {
  const clean = String(base).replace(/_copy_\d+$/, '')
  let index = 1
  let candidate = `${clean}_copy_${index}`

  while (props.definition.fields.some(field => field.name === candidate)) {
    index++
    candidate = `${clean}_copy_${index}`
  }

  return candidate
}

function changed() {
  props.definition.fields = props.definition.fields.map(normalizeField)
  emit('changed')
}
</script>
