<template>
  <ICard>
    <ICardHeader>
      <ICardHeading text="Relations" />
      <ICardActions>
        <IButton basic icon="PlusSolid" text="Add relation" @click="addRelation" />
      </ICardActions>
    </ICardHeader>

    <ICardBody class="space-y-4">
      <div
        v-for="(relation, index) in definition.relations"
        :key="relation.name || index"
        class="rounded-lg border border-neutral-200 p-4 dark:border-neutral-700"
      >
        <div class="mb-4 flex items-center justify-between gap-3">
          <div>
            <ITextDark class="font-medium" :text="relation.name || 'relation'" />
            <IText class="text-sm" :text="relation.type" />
          </div>

          <IButton basic icon="Trash" text="Remove" @click="removeRelation(index)" />
        </div>

        <div class="grid gap-4 md:grid-cols-3">
          <IFormGroup label="Name">
            <IFormInput v-model="relation.name" @input="changed" />
          </IFormGroup>

          <IFormGroup label="Type">
            <IFormSelect v-model="relation.type" @change="changed">
              <option v-for="type in relationTypes" :key="type" :value="type">
                {{ type }}{{ futureTypes.includes(type) ? ' (future)' : '' }}
              </option>
            </IFormSelect>
          </IFormGroup>

          <IFormGroup label="Target module">
            <IFormInput v-model="relation.targetModule" @input="changed" />
          </IFormGroup>

          <IFormGroup label="Target model">
            <IFormInput v-model="relation.targetModel" @input="changed" />
          </IFormGroup>

          <IFormGroup label="Target resource">
            <IFormInput v-model="relation.targetResource" @input="changed" />
          </IFormGroup>

          <IFormGroup label="Local key">
            <IFormInput v-model="relation.localKey" @input="changed" />
          </IFormGroup>

          <IFormGroup label="Foreign key">
            <IFormInput v-model="relation.foreignKey" @input="changed" />
          </IFormGroup>

          <IFormGroup label="Display field">
            <IFormInput v-model="relation.displayField" @input="changed" />
          </IFormGroup>

          <IFormGroup label="On delete">
            <IFormInput v-model="relation.onDelete" @input="changed" />
          </IFormGroup>
        </div>

        <IAlert v-if="futureTypes.includes(relation.type)" class="mt-4" variant="warning">
          <IAlertBody>
            This relation type is future/preview warning only.
          </IAlertBody>
        </IAlert>

        <div class="mt-4 grid gap-3 md:grid-cols-4">
          <IFormCheckboxField v-for="key in booleanKeys" :key="key">
            <IFormCheckbox v-model:checked="relation[key]" @change="changed" />
            <IFormCheckboxLabel :text="key" />
          </IFormCheckboxField>
        </div>
      </div>

      <IText v-if="definition.relations.length === 0" text="No relations declared." />
    </ICardBody>
  </ICard>
</template>

<script setup>
const props = defineProps({
  definition: { type: Object, required: true },
})

const emit = defineEmits(['changed'])

const relationTypes = [
  'belongsTo',
  'hasMany',
  'hasOne',
  'belongsToMany',
  'morphMany',
  'morphToMany',
]
const futureTypes = ['hasOne', 'belongsToMany', 'morphMany', 'morphToMany']
const booleanKeys = [
  'required',
  'nullable',
  'showOnDetail',
  'showOnIndex',
  'generateField',
  'generateMigrationColumn',
  'generateFrontendPanel',
]

function addRelation() {
  props.definition.relations.push({
    name: `relation${props.definition.relations.length + 1}`,
    type: 'belongsTo',
    targetModule: '',
    targetModel: '',
    targetResource: '',
    localKey: 'id',
    foreignKey: '',
    displayField: '',
    required: false,
    nullable: true,
    onDelete: 'null',
    showOnDetail: true,
    showOnIndex: false,
    generateField: true,
    generateMigrationColumn: true,
    generateFrontendPanel: false,
  })
  changed()
}

function removeRelation(index) {
  props.definition.relations.splice(index, 1)
  changed()
}

function changed() {
  emit('changed')
}
</script>
