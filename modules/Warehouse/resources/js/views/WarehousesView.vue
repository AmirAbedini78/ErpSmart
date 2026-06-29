<template>
  <MainLayout :overlay="!componentReady">
    <template #actions>
      <NavbarSeparator class="hidden lg:block" />

      <NavbarItems>
        <IButton
          basic
          icon="ChevronLeft"
          :text="backButtonText"
          @click="goBackToIndex"
        />

        <IButton
          v-if="safeResource.authorizations.update"
          variant="primary"
          icon="PencilSquareSolid"
          :text="editButtonText"
          @click="openEditFloatingModal"
        />
      </NavbarItems>
    </template>

    <div v-if="componentReady" class="mx-auto max-w-5xl">
      <ICard class="mb-6">
        <ICardBody>
          <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <ITextDisplay :text="safeResource.display_name || safeResource.name" />

              <IText
                v-if="safeResource.code"
                class="mt-1"
                :text="safeResource.code"
              />
            </div>

            <IBadge
              v-if="hasIsActiveStatus"
              :variant="safeResource.is_active ? 'success' : 'neutral'"
              :text="
                safeResource.is_active
                  ? $t('warehouse::warehouse.active')
                  : $t('warehouse::warehouse.inactive')
              "
            />
          </div>
        </ICardBody>
      </ICard>

      <div class="lg:grid lg:grid-cols-12 lg:gap-8">
        <div class="col-span-4">
          <Panels
            v-slot="{ panel }"
            v-model:panels="page.panels"
            :identifier="resourceName"
          >
            <div class="mb-3">
              <component
                :is="panel.component"
                :resource-name="resourceName"
                :resource-id="safeResource.id"
                :resource="safeResource"
                :panel="panel"
                @updated="synchronizeResource($event, true)"
              />
            </div>
          </Panels>
        </div>

        <div class="col-span-8 mt-4 lg:mt-0">
          <ITabGroup :default-index="defaultTabIndex">
            <ICard
              class="has-[[data-headlessui-state=selected]:not(:first-child)]:rounded-b-none"
            >
              <ITabList
                class="has-[[data-headlessui-state=selected]:not(:first-child)]:pb-2.5 has-[[data-headlessui-state=selected]:not(:first-child)]:sm:pb-0"
                centered
              >
                <component
                  :is="tabComponents[tab.component] || tab.component"
                  v-for="tab in page.tabs"
                  :key="tab.id"
                  :resource-name="resourceName"
                  :resource-id="safeResource.id"
                  :resource="safeResource"
                />
              </ITabList>
            </ICard>

            <ITabPanels class="[&_[data-slot=panel]]:-mt-[18px]">
              <component
                :is="tabComponents[tab.panelComponent] || tab.panelComponent"
                v-for="tab in page.tabs"
                :id="'tabPanel-' + tab.id"
                :key="tab.id"
                scroll-element="#main"
                :resource-name="resourceName"
                :resource-id="safeResource.id"
                :resource="safeResource"
              />
            </ITabPanels>
          </ITabGroup>
        </div>
      </div>
    </div>

    <RouterView @updated="fetchResource" />
  </MainLayout>
</template>

<script setup>
import { computed, provide, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import Panels from '@/Core/components/Panels.vue'
import { usePageTitle } from '@/Core/composables/usePageTitle'
import { useResource } from '@/Core/composables/useResource'
import { useFloatingResourceModal } from '@/Core/composables/useFloatingResourceModal'
import { useGlobalEventListener } from '@/Core/composables/useGlobalEventListener'
import RecordTabNote from '@/Notes/components/RecordTabNote.vue'
import RecordTabNotePanel from '@/Notes/components/RecordTabNotePanel.vue'
import ActivitiesTab from '@/Activities/components/RecordTabActivity.vue'
import ActivitiesTabPanel from '@/Activities/components/RecordTabActivityPanel.vue'

const route = useRoute()
const router = useRouter()
const resourceName = Innoclapps.resourceName('warehouses')
const warehouseId = computed(() => route.params.id)
const resourcePath = computed(() => `/${resourceName}/${warehouseId.value}`)

const tabComponents = {
  'activities-tab': ActivitiesTab,
  'activities-tab-panel': ActivitiesTabPanel,
  'notes-tab': RecordTabNote,
  'notes-tab-panel': RecordTabNotePanel,
}

function translateText(key, fallback) {
  const translated = Innoclapps.t ? Innoclapps.t(key) : null

  return typeof translated === 'string' ? translated : fallback
}

const backButtonText = computed(() =>
  translateText('warehouse::warehouse.back_to_warehouses', 'Back to warehouses')
)

const editButtonText = computed(() => translateText('core::app.edit', 'Edit'))

function goBackToIndex() {
  router.push({ name: 'warehouse-index' })
}

const { floatResourceInEditMode } = useFloatingResourceModal()

function openEditFloatingModal() {
  floatResourceInEditMode({
    resourceName,
    resourceId: Number(warehouseId.value) || warehouseId.value,
  })
}

async function refreshWarehouseDetailAfterFloatingUpdate({
  resourceName: updatedResourceName,
  resourceId,
  resource: updatedResource,
} = {}) {
  if (updatedResourceName !== resourceName) {
    return
  }

  if (String(resourceId) !== String(warehouseId.value)) {
    return
  }

  if (updatedResource) {
    synchronizeResource(normalizeResource(updatedResource), true)
  }

  try {
    await fetchResource()
    normalizeCurrentResource()
  } catch (error) {
    console.error('Failed to refresh warehouse detail after floating edit.', error)
  }
}

useGlobalEventListener(
  'floating-resource-updated',
  refreshWarehouseDetailAfterFloatingUpdate
)

const {
  resource,
  fetchResource,
  synchronizeResource,
  incrementResourceCount,
  decrementResourceCount,
  detachResourceAssociations,
  resourceInformation,
  resourceReady,
} = useResource(resourceName, warehouseId)

const page = ref(resourceInformation.value.detailPage)

const componentReady = computed(() => resourceReady.value)

const safeResource = computed(() => normalizeResource(resource.value || {}))

const hasIsActiveStatus = computed(() =>
  Object.prototype.hasOwnProperty.call(safeResource.value, 'is_active')
)

const defaultTabIndex = computed(() => {
  if (!route.query.section) {
    return 0
  }

  const index = page.value.tabs.findIndex(tab => tab.id === route.query.section)

  return index === -1 ? 0 : index
})

provide('fetchResource', fetchResource)
provide('synchronizeResource', synchronizeResource)
provide('detachResourceAssociations', detachResourceAssociations)
provide('incrementResourceCount', incrementResourceCount)
provide('decrementResourceCount', decrementResourceCount)
provide('record', safeResource)
provide('resource', safeResource)
provide('resourceName', resourceName)
provide('resourceId', warehouseId)

usePageTitle(
  computed(() => safeResource.value.display_name || safeResource.value.name)
)

let prepareRequestId = 0

async function prepareComponent() {
  const requestId = ++prepareRequestId

  await fetchResource()

  if (requestId !== prepareRequestId) {
    return
  }

  normalizeCurrentResource()
}

function normalizeCurrentResource() {
  resource.value = normalizeResource(resource.value || {})
}

function normalizeResource(value) {
  return {
    ...value,

    // Core record-tab components, including Notes, build their URL from
    // resource.path. Some custom resource responses do not include this key,
    // so Warehouse must provide it explicitly to avoid /api/undefined/notes.
    path: value.path || resourcePath.value,

    notes: Array.isArray(value.notes) ? value.notes : [],
    notes_count: Number(value.notes_count || 0),
    activities: Array.isArray(value.activities) ? value.activities : [],
    incomplete_activities_for_user_count: Number(
      value.incomplete_activities_for_user_count || 0
    ),

    media: Array.isArray(value.media)
      ? value.media.map(media => ({
          ...media,
          authorizations: {
            delete: true,
            ...(media.authorizations || {}),
          },
        }))
      : [],
    media_count: Number(
      value.media_count || (Array.isArray(value.media) ? value.media.length : 0)
    ),

    authorizations: {
      view: true,
      update: true,
      delete: false,
      ...(value.authorizations || {}),
    },
    _edit_disabled: value._edit_disabled || {},
    _sync_timestamp: value._sync_timestamp || Date.now(),
  }
}

watch(warehouseId, prepareComponent, { immediate: true })
</script>
