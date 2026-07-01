<template>
  <ICard>
    <ICardHeader>
      <ICardHeading text="Module Identity" />
    </ICardHeader>

    <ICardBody>
      <div class="grid gap-4 md:grid-cols-2">
        <IFormGroup label="Module name">
          <IFormInput v-model="definition.module.name" @input="suggestFromModuleName" />
        </IFormGroup>

        <IFormGroup label="Namespace">
          <IFormInput
            v-model="definition.module.namespace"
            @input="markTouched('namespace')"
          />
        </IFormGroup>

        <IFormGroup label="Singular label">
          <IFormInput
            v-model="definition.module.singularLabel"
            @input="markTouched('singularLabel')"
          />
        </IFormGroup>

        <IFormGroup label="Plural label">
          <IFormInput
            v-model="definition.module.pluralLabel"
            @input="markTouched('pluralLabel')"
          />
        </IFormGroup>

        <IFormGroup label="Table">
          <IFormInput v-model="definition.module.table" @input="markTouched('table')" />
        </IFormGroup>

        <IFormGroup label="Route name">
          <IFormInput
            v-model="definition.module.routeName"
            @input="markTouched('routeName')"
          />
        </IFormGroup>

        <IFormGroup label="Resource name">
          <IFormInput
            v-model="definition.module.resourceName"
            @input="markTouched('resourceName')"
          />
        </IFormGroup>

        <IFormGroup label="Icon">
          <IFormInput v-model="definition.module.icon" @input="emitChanged" />
        </IFormGroup>

        <IFormGroup label="Model class">
          <IFormInput
            v-model="definition.resource.modelClass"
            @input="markTouched('modelClass')"
          />
        </IFormGroup>

        <IFormGroup label="Title field">
          <IFormInput v-model="definition.resource.titleField" @input="emitChanged" />
        </IFormGroup>

        <IFormGroup label="Order by">
          <IFormInput v-model="definition.resource.orderBy" @input="emitChanged" />
        </IFormGroup>

        <IFormGroup label="Global search action">
          <IFormSelect v-model="definition.resource.globalSearchAction" @change="emitChanged">
            <option value="float">float</option>
            <option value="navigate">navigate</option>
            <option value="none">none</option>
          </IFormSelect>
        </IFormGroup>
      </div>

      <div class="mt-4">
        <IFormCheckboxField>
          <IFormCheckbox
            v-model:checked="definition.resource.hasDetailView"
            @change="syncDetailCapability"
          />
          <IFormCheckboxLabel text="Has detail view" />
        </IFormCheckboxField>
      </div>
    </ICardBody>
  </ICard>
</template>

<script setup>
import { reactive } from 'vue'

const props = defineProps({
  definition: { type: Object, required: true },
})

const emit = defineEmits(['changed'])

const touched = reactive({
  namespace: false,
  singularLabel: false,
  pluralLabel: false,
  table: false,
  routeName: false,
  resourceName: false,
  modelClass: false,
})

function suggestFromModuleName() {
  const moduleName = pascal(props.definition.module.name || 'CustomRecords')
  const singular = singularize(moduleName)
  const plural = moduleName

  props.definition.module.name = moduleName

  if (!touched.namespace) {
    props.definition.module.namespace = `Modules\\${moduleName}`
  }

  if (!touched.singularLabel) {
    props.definition.module.singularLabel = singular
  }

  if (!touched.pluralLabel) {
    props.definition.module.pluralLabel = plural
  }

  if (!touched.table) {
    props.definition.module.table = snake(plural)
  }

  if (!touched.routeName) {
    props.definition.module.routeName = kebab(plural)
  }

  if (!touched.resourceName) {
    props.definition.module.resourceName = kebab(plural)
  }

  if (!touched.modelClass) {
    props.definition.resource.modelClass = `Modules\\${moduleName}\\Models\\${singular}`
  }

  emitChanged()
}

function markTouched(key) {
  touched[key] = true
  emitChanged()
}

function syncDetailCapability() {
  props.definition.capabilities.hasDetailView = Boolean(
    props.definition.resource.hasDetailView
  )
  emitChanged()
}

function emitChanged() {
  emit('changed')
}

function pascal(value) {
  return String(value)
    .replace(/[^a-zA-Z0-9]+/g, ' ')
    .split(' ')
    .filter(Boolean)
    .map(part => part.charAt(0).toUpperCase() + part.slice(1))
    .join('')
}

function snake(value) {
  return String(value)
    .replace(/([a-z0-9])([A-Z])/g, '$1_$2')
    .replace(/[^a-zA-Z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '')
    .toLowerCase()
}

function kebab(value) {
  return snake(value).replace(/_/g, '-')
}

function singularize(value) {
  return value.endsWith('s') ? value.slice(0, -1) : value
}
</script>
