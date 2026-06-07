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
          :to="{ name: 'create-package' }"
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
          to: { name: 'create-package' },
          title: $t('saas::saas.fields.package.empty_state.title'),
          buttonText: $t('saas::saas.create.package'),
          description: $t('saas::saas.fields.package.empty_state.description'),
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
            :to="`/saas/packages/${row.id}/edit`"
          >
            <Icon icon="Pencil" />
          </ILink>
        </template>

        <template #invoice_number="{ row, column }">
          <a
            class="mr-1.5 text-base/6 text-primary-600 no-underline last:mr-0 hover:text-primary-900 focus:outline-none dark:text-primary-300 dark:hover:text-primary-400 sm:text-sm/6"
            target="_blank"
            :href="`/invoices/${row.id}/pay`"
          >
            {{ row.invoice_number }}
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

const resourceName = 'packages'
const resourceInformation = Innoclapps.resource(resourceName)
const { t } = useI18n()

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
