<template>
  <MainLayout>
    <template #actions>
      <NavbarSeparator class="hidden lg:block" />

      <NavbarItems>
        <IDropdownMinimal :placement="tableEmpty ? 'bottom-end' : 'bottom'" horizontal>
          <IDropdownItem v-if="resourceInformation.authorizedToImport" icon="DocumentAdd" :to="{
            name: 'import-resource',
            params: { resourceName },
          }" :text="$t('core::resource.import', {
              resource: resourceInformation.label,
            })
              " />

          <IDropdownItem v-if="resourceInformation.authorizedToExport" icon="DocumentDownload" :text="$t('core::resource.export', {
            resource: resourceInformation.label,
          })
            " @click="$dialog.show('export-modal')" />

          <IDropdownItem v-if="resourceInformation.usesSoftDeletes" icon="Trash" :to="{
            name: 'trashed-resource-records',
            params: { resourceName },
          }" :text="$t('core::resource.trashed', {
              resource: resourceInformation.label,
            })
              " />
        </IDropdownMinimal>

        <IButton v-show="!tableEmpty" variant="primary" icon="PlusSolid"
          :disabled="!resourceInformation.authorizedToCreate" :to="{ name: 'create-quota' }" :text="$t('core::resource.create', {
            resource: resourceInformation.singularLabel,
          })
            " />
      </NavbarItems>
    </template>

    <IOverlay v-if="!tableLoaded" show />


    <div v-if="shouldShowEmptyState" class="m-auto mt-8 max-w-5xl">
      <IEmptyState v-bind="{
        to: { name: 'create-quota' },
        title: $t('You have not created any Quota'),
        buttonText: $t('Create Quota'),
        description: $t('Save time by using predefined quotas.'),
        secondButtonText: $t('Import from CSV file', { file_type: 'CSV' }),
        secondButtonIcon: 'DocumentAdd',
        secondButtonTo: {
          name: 'import-resource',
          params: { resourceName },
        },
      }" />
    </div>

    <div v-show="!tableEmpty">
      <ResourceTable :resource-name="resourceName" @loaded="handleTableLoaded" />
    </div>

    <ResourceExport :resource-name="resourceName" />

    <!-- Create -->
    <RouterView :redirect-to-view="true" @created="({ isRegularAction }) => (!isRegularAction ? refreshIndex() : undefined)
      " @hidden="$router.back" />
  </MainLayout>
</template>

<script setup>
import { computed, ref } from "vue";

import { useTable } from "@/Core/composables/useTable";

const resourceName = "quotas";
const resourceInformation = Innoclapps.resource(resourceName);

const { reloadTable } = useTable(resourceName);

const tableEmpty = ref(true);
const tableLoaded = ref(false);

const shouldShowEmptyState = computed(
  () => tableEmpty.value && tableLoaded.value
);

function handleTableLoaded(e) {
  tableEmpty.value = e.isPreEmpty;
  tableLoaded.value = true;
}

function refreshIndex() {
  reloadTable();
}
</script>
