<template>
  <ICard>
    <ICardHeader>
      <ICardHeading text="Automation" />
    </ICardHeader>

    <ICardBody class="space-y-5">
      <IAlert variant="warning">
        <IAlertBody>
          Automation metadata only; runtime workflow engine is future work.
        </IAlertBody>
      </IAlert>

      <IFormCheckboxField>
        <IFormCheckbox
          v-model:checked="automation.enabled"
          @change="changed"
        />
        <IFormCheckboxLabel text="Enable automation metadata" />
      </IFormCheckboxField>

      <div class="flex items-center justify-between gap-3">
        <ITextDark class="font-medium" text="Workflows" />
        <IButton basic icon="PlusSolid" text="Add workflow" @click="addWorkflow" />
      </div>

      <div class="space-y-4">
        <div
          v-for="(workflow, workflowIndex) in automation.workflows"
          :key="workflow.id || workflowIndex"
          class="rounded-lg border border-neutral-200 p-4 dark:border-neutral-700"
        >
          <div class="mb-4 flex items-center justify-between gap-3">
            <div>
              <ITextDark class="font-medium" :text="workflow.name || workflow.id" />
              <IText class="text-sm" :text="workflow.enabled ? 'enabled' : 'disabled'" />
            </div>

            <IButton basic icon="Trash" text="Remove" @click="removeWorkflow(workflowIndex)" />
          </div>

          <div class="grid gap-4 md:grid-cols-3">
            <IFormGroup label="Workflow ID">
              <IFormInput v-model="workflow.id" @input="changed" />
            </IFormGroup>

            <IFormGroup label="Name">
              <IFormInput v-model="workflow.name" @input="changed" />
            </IFormGroup>

            <IFormCheckboxField class="self-end">
              <IFormCheckbox v-model:checked="workflow.enabled" @change="changed" />
              <IFormCheckboxLabel text="Enabled" />
            </IFormCheckboxField>

            <IFormGroup label="Description" class="md:col-span-3">
              <IFormTextarea v-model="workflow.description" rows="2" @input="changed" />
            </IFormGroup>
          </div>

          <div class="mt-5 rounded-md bg-neutral-50 p-4 dark:bg-neutral-900">
            <ITextDark class="mb-3 font-medium" text="Trigger" />

            <div class="grid gap-4 md:grid-cols-3">
              <IFormGroup label="Type">
                <IFormSelect v-model="workflow.trigger.type" @change="changed">
                  <option value="record_created">record_created</option>
                  <option value="record_updated">record_updated</option>
                  <option value="field_changed">field_changed</option>
                  <option value="status_changed">status_changed</option>
                  <option value="manual">manual</option>
                </IFormSelect>
              </IFormGroup>

              <IFormGroup label="Field">
                <IFormSelect v-model="workflow.trigger.field" @change="changed">
                  <option value="">Select field</option>
                  <option v-for="field in fieldOptions" :key="field.name" :value="field.name">
                    {{ field.label || field.name }}
                  </option>
                </IFormSelect>
              </IFormGroup>

              <IFormGroup label="Value">
                <IFormInput v-model="workflow.trigger.value" @input="changed" />
              </IFormGroup>
            </div>

            <div class="mt-4 grid gap-3 md:grid-cols-2">
              <IFormCheckboxField v-for="mode in triggerModes" :key="mode">
                <IFormCheckbox
                  :checked="workflow.trigger.modes.includes(mode)"
                  @change="toggleArrayValue(workflow.trigger.modes, mode)"
                />
                <IFormCheckboxLabel :text="`Applies on ${mode}`" />
              </IFormCheckboxField>
            </div>
          </div>

          <div class="mt-5">
            <div class="mb-3 flex items-center justify-between gap-3">
              <ITextDark class="font-medium" text="Conditions" />
              <IButton basic icon="PlusSolid" text="Add condition" @click="addCondition(workflow)" />
            </div>

            <div class="space-y-3">
              <div
                v-for="(condition, conditionIndex) in workflow.conditions"
                :key="condition.id || conditionIndex"
                class="rounded-md bg-neutral-50 p-3 dark:bg-neutral-900"
              >
                <div class="mb-3 flex items-center justify-between gap-3">
                  <ITextDark class="font-medium" :text="condition.id" />
                  <IButton basic icon="Trash" text="Remove" @click="removeCondition(workflow, conditionIndex)" />
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                  <IFormGroup label="Condition ID">
                    <IFormInput v-model="condition.id" @input="changed" />
                  </IFormGroup>

                  <IFormGroup label="Field">
                    <IFormSelect v-model="condition.field" @change="changed">
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
                      <option value="greater_than">greater_than</option>
                      <option value="less_than">less_than</option>
                    </IFormSelect>
                  </IFormGroup>

                  <IFormGroup label="Value">
                    <IFormInput v-model="condition.value" @input="changed" />
                  </IFormGroup>

                  <IFormGroup label="Join">
                    <IFormSelect v-model="condition.join" @change="changed">
                      <option value="and">and</option>
                      <option value="or">or</option>
                    </IFormSelect>
                  </IFormGroup>
                </div>
              </div>

              <IText v-if="workflow.conditions.length === 0" text="No conditions declared." />
            </div>
          </div>

          <div class="mt-5">
            <div class="mb-3 flex items-center justify-between gap-3">
              <ITextDark class="font-medium" text="Actions" />
              <IButton basic icon="PlusSolid" text="Add action" @click="addAction(workflow)" />
            </div>

            <div class="space-y-3">
              <div
                v-for="(action, actionIndex) in workflow.actions"
                :key="action.id || actionIndex"
                class="rounded-md bg-neutral-50 p-3 dark:bg-neutral-900"
              >
                <IAlert class="mb-3" variant="warning">
                  <IAlertBody>
                    Metadata only; this action will not execute in MVP.
                  </IAlertBody>
                </IAlert>

                <div class="mb-3 flex items-center justify-between gap-3">
                  <ITextDark class="font-medium" :text="action.label || action.id" />
                  <div class="flex gap-2">
                    <IButton basic text="Up" @click="moveAction(workflow, actionIndex, -1)" />
                    <IButton basic text="Down" @click="moveAction(workflow, actionIndex, 1)" />
                    <IButton basic icon="Trash" text="Remove" @click="removeAction(workflow, actionIndex)" />
                  </div>
                </div>

                <div class="grid gap-4 md:grid-cols-4">
                  <IFormGroup label="Action ID">
                    <IFormInput v-model="action.id" @input="changed" />
                  </IFormGroup>

                  <IFormGroup label="Type">
                    <IFormSelect v-model="action.type" @change="actionTypeChanged(action)">
                      <option value="create_task">create_task</option>
                      <option value="send_email">send_email</option>
                      <option value="send_notification">send_notification</option>
                      <option value="request_approval">request_approval</option>
                      <option value="webhook">webhook</option>
                    </IFormSelect>
                  </IFormGroup>

                  <IFormGroup label="Label">
                    <IFormInput v-model="action.label" @input="changed" />
                  </IFormGroup>

                  <IFormCheckboxField class="self-end">
                    <IFormCheckbox v-model:checked="action.enabled" @change="changed" />
                    <IFormCheckboxLabel text="Enabled" />
                  </IFormCheckboxField>
                </div>

                <div class="mt-4 grid gap-4 md:grid-cols-3">
                  <template v-if="action.type === 'create_task'">
                    <IFormGroup label="Task title">
                      <IFormInput v-model="action.config.taskTitle" @input="changed" />
                    </IFormGroup>

                    <IFormGroup label="Task due in days">
                      <IFormInput v-model.number="action.config.taskDueInDays" type="number" @input="changed" />
                    </IFormGroup>
                  </template>

                  <template v-if="action.type === 'send_email'">
                    <IFormGroup label="Email to">
                      <IFormInput v-model="action.config.emailTo" @input="changed" />
                    </IFormGroup>

                    <IFormGroup label="Email subject">
                      <IFormInput v-model="action.config.emailSubject" @input="changed" />
                    </IFormGroup>

                    <IFormGroup label="Email template">
                      <IFormTextarea v-model="action.config.emailTemplate" rows="3" @input="changed" />
                    </IFormGroup>
                  </template>

                  <template v-if="action.type === 'send_notification'">
                    <IFormGroup label="Notification message" class="md:col-span-3">
                      <IFormTextarea v-model="action.config.notificationMessage" rows="3" @input="changed" />
                    </IFormGroup>
                  </template>

                  <template v-if="action.type === 'request_approval'">
                    <IFormGroup label="Approval role">
                      <IFormInput v-model="action.config.approvalRole" @input="changed" />
                    </IFormGroup>
                  </template>

                  <template v-if="action.type === 'webhook'">
                    <IFormGroup label="Webhook URL">
                      <IFormInput v-model="action.config.webhookUrl" @input="changed" />
                    </IFormGroup>
                  </template>
                </div>
              </div>

              <IText v-if="workflow.actions.length === 0" text="No actions declared." />
            </div>
          </div>
        </div>

        <IText v-if="automation.workflows.length === 0" text="No workflows declared." />
      </div>
    </ICardBody>
  </ICard>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  definition: { type: Object, required: true },
})

const emit = defineEmits(['changed'])

const triggerModes = ['create', 'update']
const automation = computed(() => props.definition.automation)
const fieldOptions = computed(() =>
  props.definition.fields.filter(field => field.name && field.name !== 'id')
)

function addWorkflow() {
  const index = automation.value.workflows.length + 1

  automation.value.enabled = true
  automation.value.workflows.push({
    id: `workflow_${index}`,
    name: `Workflow ${index}`,
    description: '',
    enabled: true,
    trigger: {
      type: 'record_created',
      field: '',
      value: '',
      modes: ['create'],
    },
    conditions: [],
    actions: [],
  })

  props.definition.capabilities.workflow = true
  changed()
}

function removeWorkflow(index) {
  automation.value.workflows.splice(index, 1)
  changed()
}

function addCondition(workflow) {
  const index = workflow.conditions.length + 1

  workflow.conditions.push({
    id: `condition_${index}`,
    field: fieldOptions.value[0]?.name || '',
    operator: 'equals',
    value: '',
    join: 'and',
  })

  changed()
}

function removeCondition(workflow, index) {
  workflow.conditions.splice(index, 1)
  changed()
}

function addAction(workflow) {
  const index = workflow.actions.length + 1

  workflow.actions.push({
    id: `action_${index}`,
    type: 'create_task',
    enabled: true,
    label: `Action ${index}`,
    order: index,
    config: defaultActionConfig(),
  })

  props.definition.capabilities.tasks = true
  changed()
}

function removeAction(workflow, index) {
  workflow.actions.splice(index, 1)
  reorder(workflow.actions)
  changed()
}

function moveAction(workflow, index, direction) {
  const nextIndex = index + direction

  if (nextIndex < 0 || nextIndex >= workflow.actions.length) {
    return
  }

  const [action] = workflow.actions.splice(index, 1)
  workflow.actions.splice(nextIndex, 0, action)
  reorder(workflow.actions)
  changed()
}

function actionTypeChanged(action) {
  action.config ||= {}

  Object.assign(action.config, defaultActionConfig(), action.config)

  props.definition.capabilities.tasks ||= action.type === 'create_task'
  props.definition.capabilities.emails ||= action.type === 'send_email'
  props.definition.capabilities.emailSending ||= action.type === 'send_email'
  props.definition.capabilities.notifications ||= action.type === 'send_notification'
  props.definition.capabilities.approvals ||= action.type === 'request_approval'
  props.definition.capabilities.workflow = true

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

function reorder(items) {
  items.forEach((item, index) => {
    item.order = index + 1
  })
}

function defaultActionConfig() {
  return {
    taskTitle: '',
    taskDueInDays: 1,
    emailTo: '',
    emailSubject: '',
    emailTemplate: '',
    notificationMessage: '',
    approvalRole: '',
    webhookUrl: '',
  }
}

function changed() {
  automation.value.workflows.forEach(workflow => {
    workflow.trigger ||= { type: 'record_created', field: '', value: '', modes: ['create'] }
    workflow.trigger.modes ||= ['create']
    workflow.conditions ||= []
    workflow.actions ||= []
    workflow.actions.forEach((action, index) => {
      action.order ||= index + 1
      action.config = Object.assign(defaultActionConfig(), action.config || {})
    })
  })

  props.definition.capabilities.workflow = automation.value.workflows.length > 0
  props.definition.capabilities.tasks = hasActionType('create_task')
  props.definition.capabilities.emails = hasActionType('send_email')
  props.definition.capabilities.emailSending = hasActionType('send_email')
  props.definition.capabilities.notifications = hasActionType('send_notification')
  props.definition.capabilities.approvals = hasActionType('request_approval')

  emit('changed')
}

function hasActionType(type) {
  return automation.value.workflows.some(workflow =>
    workflow.actions.some(action => action.type === type)
  )
}
</script>
