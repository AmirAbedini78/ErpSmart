<template>
  <MainLayout :overlay="!componentReady">
    <template #actions>
      <NavbarSeparator class="hidden lg:block" />

      <NavbarItems>
        <IButton
          basic
          icon="ChevronLeft"
          :to="{ name: 'warehouse-index' }"
          :text="$t('warehouse::warehouse.back_to_warehouses')"
        />

        <IButton
          v-if="resource.authorizations?.update"
          variant="primary"
          icon="PencilSquareSolid"
          :to="{ name: 'edit-warehouse', params: { id: warehouseId } }"
          :text="$t('core::app.edit')"
        />
      </NavbarItems>
    </template>

    <div v-if="componentReady" class="mx-auto max-w-5xl">
      <ICard class="mb-6">
        <ICardBody>
          <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <ITextDisplay :text="resource.display_name || resource.name" />

              <IText
                v-if="resource.code"
                class="mt-1"
                :text="resource.code"
              />
            </div>

            <IBadge
              v-if="Object.hasOwn(resource, 'is_active')"
              :variant="resource.is_active ? 'success' : 'neutral'"
              :text="
                resource.is_active
                  ? $t('warehouse::warehouse.active')
                  : $t('warehouse::warehouse.inactive')
              "
            />
          </div>
        </ICardBody>
      </ICard>

      <ICard>
        <ICardHeader>
          <ICardHeading :text="$t('core::app.record_view.sections.details')" />
        </ICardHeader>

        <ICardBody>
          <FieldsPlaceholder v-if="!hasFields" />

          <DetailFields
            v-else
            :fields="fields"
            :resource-name="resourceName"
            :resource-id="Number(warehouseId)"
            :resource="resource"
            @updated="synchronizeResource($event, true)"
          />
        </ICardBody>
      </ICard>
    </div>

    <RouterView @updated="fetchResource" />
  </MainLayout>
</template>

<script setup>
import { computed, watch } from 'vue'
import { useRoute } from 'vue-router'

import { usePageTitle } from '@/Core/composables/usePageTitle'
import { useResource } from '@/Core/composables/useResource'
import { useResourceFields } from '@/Core/composables/useResourceFields'

const route = useRoute()

const resourceName = Innoclapps.resourceName('warehouses')
const warehouseId = computed(() => route.params.id)

const {
  resource,
  fetchResource,
  synchronizeResource,
  resourceReady,
} = useResource(resourceName, warehouseId)

const { fields, hasFields, getDetailFields, setResource } =
  useResourceFields()

const componentReady = computed(() => resourceReady.value && hasFields.value)

usePageTitle(computed(() => resource.value.display_name || resource.value.name))

async function prepareComponent() {
  await fetchResource()
  fields.value = await getDetailFields(resourceName, warehouseId.value)
  setResource(resource)
}

watch(warehouseId, prepareComponent, { immediate: true })
</script>

