<template>
  <MainLayout>
    <template #actions>
      <NavbarSeparator class="hidden lg:block" />

      <NavbarItems>
        <IDropdownMinimal
          :placement="tableEmpty ? 'bottom-end' : 'bottom'"
          horizontal
        >
          <IDropdownItem
            v-if="resourceInformation.authorizedToImport"
            icon="DocumentAdd"
            :to="{
              name: 'import-resource',
              params: { resourceName },
            }"
            :text="
              $t('core::resource.import', {
                resource: resourceInformation.label,
              })
            "
          />

          <IDropdownItem
            v-if="resourceInformation.authorizedToExport"
            icon="DocumentDownload"
            :text="
              $t('core::resource.export', {
                resource: resourceInformation.label,
              })
            "
            @click="$dialog.show('export-modal')"
          />

          <IDropdownItem
            v-if="resourceInformation.usesSoftDeletes"
            icon="Trash"
            :to="{
              name: 'trashed-resource-records',
              params: { resourceName },
            }"
            :text="
              $t('core::resource.trashed', {
                resource: resourceInformation.label,
              })
            "
          />
        </IDropdownMinimal>

        <IButton
          v-show="!tableEmpty"
          variant="primary"
          icon="PlusSolid"
          :disabled="!resourceInformation.authorizedToCreate"
          :to="{ name: 'create-tenant' }"
          :text="
            $t('core::resource.create', {
              resource: resourceInformation.singularLabel,
            })
          "
        />
      </NavbarItems>
    </template>

    <IOverlay v-if="!tableLoaded" show />

    <div v-if="shouldShowEmptyState" class="m-auto mt-8 max-w-5xl">
      <IEmptyState
        v-bind="{
          to: { name: 'create-tenant' },
          title: $t('saas::saas.fields.tenant.empty_state.title'),
          buttonText: $t('saas::saas.create.tenant'),
          description: $t('saas::saas.fields.tenant.empty_state.description'),
          secondButtonText: $t('core::import.from_file', { file_type: 'CSV' }),
          secondButtonIcon: 'DocumentAdd',
          secondButtonTo: {
            name: 'import-resource',
            params: { resourceName },
          },
        }"
      />
    </div>

    <div v-show="!tableEmpty">
      <ResourceTable :resource-name="resourceName" @loaded="handleTableLoaded">
        <template #name="{ row, column }">
          <span>
            {{ row.name }}
          </span>

          <ILink
            class="btn btn-secondary btn-sm absolute right-0.5 z-20 my-auto mr-4"
            :to="`/saas/tenants/${row.id}/edit`"
          >
            <Icon icon="Pencil" />
          </ILink>

        </template>

        <template #subdomain_url="{ row, column }">
          <a
            class="mr-1.5 text-base/6 text-primary-600 no-underline last:mr-0 hover:text-primary-900 focus:outline-none dark:text-primary-300 dark:hover:text-primary-400 sm:text-sm/6"
            target="_blank"
            :href="`${row.subdomain_url}`"
          >
            {{ row.subdomain_url }}
          </a>
        </template>

        <template #domain_url="{ row, column }">
          <a
            class="mr-1.5 text-base/6 text-primary-600 no-underline last:mr-0 hover:text-primary-900 focus:outline-none dark:text-primary-300 dark:hover:text-primary-400 sm:text-sm/6"
            target="_blank"
            :href="`${row.domain_url}`"
          >
            {{ row.domain_url }}
          </a>
        </template>
      </ResourceTable>
    </div>

    <ResourceExport :resource-name="resourceName" />

    <!-- Create -->
    <RouterView
      :redirect-to-view="true"
      @created="
        ({ isRegularAction }) => (!isRegularAction ? refreshIndex() : undefined)
      "
      @hidden="$router.back"
    />
  </MainLayout>
</template>

<script setup>
import { computed, ref } from 'vue'

import { useTable } from '@/Core/composables/useTable'

const resourceName = 'tenants'
const resourceInformation = Innoclapps.resource(resourceName)

const { reloadTable } = useTable(resourceName)

const tableEmpty = ref(true)
const tableLoaded = ref(false)

const shouldShowEmptyState = computed(
  () => tableEmpty.value && tableLoaded.value
)

function handleTableLoaded(e) {
  tableEmpty.value = e.isPreEmpty
  tableLoaded.value = true
}

function refreshIndex() {
  reloadTable()
}
</script>
