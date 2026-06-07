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
          :disabled="!resourceInformation.authorizedToCreate" :to="{ name: 'create-page' }" :text="$t('core::resource.create', {
            resource: resourceInformation.singularLabel,
          })
            " />
      </NavbarItems>
    </template>

<IOverlay v-if="!tableLoaded" show />


<div v-if="shouldShowEmptyState" class="m-auto mt-8 max-w-5xl">
    <IEmptyState v-bind="{
        to: { name: 'create-page' },
        title: $t('You have not created any page'),
        buttonText: $t('Create page'),
        description: $t('Save time by using predefined pages.'),
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

const resourceName = "pages";
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
</script> I am usinc concrodm crm and it used cue 2, WJat i want to do is, when I edit pages I wanto add a button for
rdit pages and then redirect to editor <template>
    <div>
        <div id="gjs"></div>
        <button @click="saveTemplate">Save Template</button>
    </div>
</template>

<script>
import grapesjs from 'grapesjs';
import 'grapesjs-blocks-basic'; 
import 'grapesjs-plugin-forms';
import { useRoute } from "vue-router";
import { mapGetters } from 'vuex';

const route = useRoute();

export default {
    name: 'GrapeJSBuilder',
    props: ['id'],
    data() {
        return {
            page: null,
            editor: null,
        };
    },
    mounted() {
        // this.loadPage();
        this.initializeGrapeJS();
    },
    methods: {
        // loadPage() {
        //     const pageId = this.$route.params.id;
        //     axios.get(/api/pages/${pageId})
        //         .then(response => {
        //             this.page = response.data;
        //         })
        //         .catch(error => {
        //             console.error('Error loading page:', error);
        //         });
        // },
        initializeGrapeJS() {
            const editor = grapesjs.init({
                container: '#gjs',
                plugins: ['gjs-blocks-basic', 'grapesjs-plugin-forms', 'grapesjs-plugin-extra'],
                fromElement: true,
                storageManager: false,
            });

            this.editor = editor;

            // Organize components into categories
            const blockManager = editor.BlockManager;

            // Basic Components
            blockManager.add('header', {
                label: 'Header',
                category: 'Basic',
                content: '<header style="padding: 20px; background: #f0f0f0; text-align: center;">Welcome to My Website</header>',
            });

            blockManager.add('button', {
                label: 'Button',
                category: 'Basic',
                content: '<button style="padding: 10px 20px; background: #007bff; color: #fff; border: none; border-radius: 5px;">Click Me</button>',
            });

            blockManager.add('image', {
                label: 'Image',
                category: 'Basic',
                content: '<img src="https://via.placeholder.com/150" alt="Placeholder Image" style="max-width: 100%; height: auto;">',
            });

            blockManager.add('text', {
                label: 'Text',
                category: 'Basic',
                content: '<p style="font-size: 16px; color: #333;">This is a sample text block. You can edit it!</p>',
            });

            blockManager.add('footer', {
                label: 'Footer',
                category: 'Basic',
                content: '<footer style="padding: 20px; background: #333; color: #fff; text-align: center;">© 2023 My Company</footer>',
            });

            // Form Components (from grapesjs-plugin-forms)
            blockManager.add('input', {
                label: 'Input',
                category: 'Forms',
                content: '<input type="text" placeholder="Enter text" style="padding: 10px; width: 100%;">',
            });

            blockManager.add('textarea', {
                label: 'Textarea',
                category: 'Forms',
                content: '<textarea placeholder="Enter text" style="padding: 10px; width: 100%;"></textarea>',
            });

            blockManager.add('select', {
                label: 'Select',
                category: 'Forms',
                content: '<select style="padding: 10px; width: 100%;"><option value="1">Option 1</option><option value="2">Option 2</option></select>',
            });

            blockManager.add('checkbox', {
                label: 'Checkbox',
                category: 'Forms',
                content: '<input type="checkbox" style="margin-right: 10px;"> Checkbox',
            });

            blockManager.add('radio', {
                label: 'Radio',
                category: 'Forms',
                content: '<input type="radio" style="margin-right: 10px;"> Radio',
            });

            // Extra Components (from grapesjs-plugin-extra)
            blockManager.add('video', {
                label: 'Video',
                category: 'Extras',
                content: '<iframe width="560" height="315" src="https://www.youtube.com/embed/dQw4w9WgXcQ" frameborder="0" allowfullscreen></iframe>',
            });

            blockManager.add('map', {
                label: 'Map',
                category: 'Extras',
                content: '<iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3153.8354345093747!2d144.9537353153166!3d-37.816279742021665!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x6ad642af0f11fd81%3A0xf577d8a32f7f8c8!2sMelbourne%20VIC%2C%20Australia!5e0!3m2!1sen!2sus!4v1625061234567!5m2!1sen!2sus" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy"></iframe>',
            });

            blockManager.add('icon', {
                label: 'Icon',
                category: 'Extras',
                content: '<i class="fa fa-star" style="font-size: 24px; color: gold;"></i>',
            });

            // Set initial components for the editor
            editor.setComponents(
                <header style="padding: 20px; background: #f0f0f0; text-align: center;">Welcome to My Website</header>
                <div style="padding: 20px;">
                    <h1>Hello, World!</h1>
                    <p>This is a pre-built page structure. You can start editing it right away!</p>
                    <img src="https://via.placeholder.com/150" alt="Placeholder Image" style="max-width: 100%; height: auto;">
                    <button style="padding: 10px 20px; background: #007bff; color: #fff; border: none; border-radius: 5px;">Click Me</button>
                </div>
                <footer style="padding: 20px; background: #333; color: #fff; text-align: center;">© 2023 My Company</footer>
            );
        },
        saveTemplate() {
            const html = this.editor.getHtml();
            const css = this.editor.getCss();
            console.log('HTML:', html);
            console.log('CSS:', css);
            // Save the template to your backend or database
        },
    },
};
</script>

<style>
/* Add custom styles if needed */
</style>