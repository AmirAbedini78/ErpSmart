<template>
  <MainLayout>
    <template #actions>
      <NavbarSeparator class="hidden lg:block" />

      <NavbarItems>
        <!-- Other items -->

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
          <!-- Other DropdownItems -->

          <!-- Export option that opens export modal -->
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
          :to="{ name: 'create-page' }"
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
          to: { name: 'create-page' },
          title: $t('You have not created any Page'),
          buttonText: $t('saas::saas.create.page'),
          description: $t('saas::saas.fields.pages.empty_state.description'),
          secondButtonText: $t('core::import.from_file', { file_type: 'CSV' }),
          secondButtonTo: {
            name: 'import-resource',
            params: { resourceName },
          },
        }"
      />
    </div>

    <div v-show="!tableEmpty">
      <ResourceTable :resource-name="resourceName" @loaded="handleTableLoaded">
        <template #name="{ row }">
          <Button @click="navigateToEditor(row.id)">
            {{ row.name }}
          </Button>
        </template>

        <template #template_id="{ row }">
          <div class="flex">
            <a
              target="_blank"
              rel="noopener noreferrer"
              :href="`/pages/${row.id}/preview`"
            >
              <Icon
                v-i-tooltip.bottom.light="t('saas::saas.preview_page')"
                icon="Eye"
                class="ml-2 mt-px size-6 cursor-pointer"
                @click="openModal(row)"
              />
            </a>

            <Icon
              v-i-tooltip.bottom.light="t('saas::saas.upload_template')"
              v-dialog="'templateUpload'"
              icon="CloudArrowUp"
              class="ml-2 mt-px size-6 cursor-pointer"
              @click="openModal(row)"
            />
          </div>
        </template>
      </ResourceTable>
    </div>

    <IModal
      id="templateUpload"
      v-model="showUploadModal"
      size="sm"
      :title="$t('Upload Template')"
      hide-footer
    >
      <div class="flex flex-col space-y-4 py-3">
        <ITextDark
          class="font-semibold"
          text="Please refer to the sample file for guidance..."
        />

        <IButton
          icon="DocumentDownload"
          :text="$t('core::import.download_sample')"
          basic
          @click="downloadSample(`/api/saas/download`)"
        />

        <input
          ref="fileInput"
          type="file"
          accept=".zip"
          class=""
          :class="
            twMerge(
              [
                'block w-full rounded-lg border-0 text-base/6 text-neutral-900 shadow-sm placeholder:text-neutral-500/80 disabled:bg-neutral-200 dark:bg-neutral-500/10 dark:text-white dark:placeholder-neutral-300/70 dark:disabled:bg-neutral-700/10',

                'ring-1 ring-inset ring-neutral-300 focus:ring-2 focus:ring-inset focus:ring-primary-600 dark:ring-neutral-500/30 dark:focus:ring-primary-600',

                'px-3.5 py-2.5 sm:px-3 sm:py-1.5 sm:text-sm/6',
              ],
              ['rounded border p-2']
            )
          "
          @change="handleFileChange"
        />

        <p v-if="errorMessage" class="text-red-500 text-sm">
          {{ errorMessage }}
        </p>

        <IButton
          variant="primary"
          :disabled="!selectedFile"
          @click="submitUpload"
        >
          {{ t('Upload') }}
        </IButton>
      </div>
    </IModal>

    <ResourceExport :resource-name="resourceName" />

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
import { useRouter } from 'vue-router'
import axios from 'axios'
import { twMerge } from 'tailwind-merge'

import { useTable } from '@/Core/composables/useTable'

const resourceName = Innoclapps.resourceName('pages')
const resourceInformation = Innoclapps.resource(resourceName)

const { reloadTable } = useTable(resourceName)
const showUploadModal = ref(false)
const selectedFile = ref(null)
const errorMessage = ref('')
const selectedRow = ref(null) // Store the selected row for file upload
const { t } = useI18n()
const tableEmpty = ref(true)
const tableLoaded = ref(false)
const router = useRouter()

const shouldShowEmptyState = computed(
  () => tableEmpty.value && tableLoaded.value
)

async function downloadSample(url) {
  try {
    const response = await axios.get(url, {
      responseType: 'blob',
    })

    const link = document.createElement('a')
    link.href = URL.createObjectURL(response.data)
    link.download = 'sample.zip'

    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
  } catch (error) {
    console.error('Download failed:', error)
  }
}

function handleFileChange(event) {
  const file = event.target.files[0]

  if (file && file.type === 'application/zip') {
    selectedFile.value = file
    errorMessage.value = ''
  } else {
    selectedFile.value = null
    errorMessage.value = 'Please select a valid ZIP file.'
  }
}

function openModal(row) {
  selectedRow.value = row
  showUploadModal.value = true
}

async function submitUpload() {
  if (!selectedFile.value) {
    errorMessage.value = 'Please select a file to upload.'

    return
  }

  try {
    const formData = new FormData()
    formData.append('template', selectedFile.value)
    formData.append('row_id', selectedRow.value.id)

    await axios.post(
      `/api/saas/templates/upload/${selectedRow.value.id}`,
      formData,
      {
        headers: { 'Content-Type': 'multipart/form-data' },
      }
    )

    Innoclapps.success('Template uploaded successfully!')
    showUploadModal.value = false
    selectedFile.value = null
  } catch (error) {
    console.error('Error uploading template:', error)
    errorMessage.value = 'Failed to upload template. Please try again.'
  }
}

// Handle when the table has finished loading
function handleTableLoaded(e) {
  tableEmpty.value = e.isPreEmpty
  tableLoaded.value = true
}

// Navigate to the editor page
function navigateToEditor(id) {
  router.push({ name: 'grapejs-edit', params: { id } })
}

// Refresh the table after actions
function refreshIndex() {
  reloadTable()
}
</script>
