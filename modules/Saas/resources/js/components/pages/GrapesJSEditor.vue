<template>
    <div class="builder-container">
        <div id="gjs" v-once></div>
        <button @click="saveTemplate" class="save-button">💾 Save Template</button>
    </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import axios from 'axios';
import grapesjs from 'grapesjs';
import 'grapesjs-blocks-basic';
import 'grapesjs-plugin-forms';
import 'grapesjs/dist/css/grapes.min.css';
import Cookies from 'js-cookie';
import { useRoute, useRouter } from 'vue-router'

const props = defineProps({
    pageId: {
        type: String,
    },
});
let editor = null;
const page = ref(null);
const route = useRoute()

const initializeGrapeJS = (extCss, extJs, markdown) => {


    const csrfToken = Cookies.get('XSRF-TOKEN');
    if (!csrfToken) {
        console.error('CSRF token is missing!');
        return;
    }

    editor = grapesjs.init({
        container: '#gjs',
        fromElement: true,
        storageManager: false,
        autorender:false,

        assetManager: {
            upload: '/api/saas/upload-image',
            uploadName: 'image',
            autoAdd: true,
            headers: {
                'X-Xsrf-token': csrfToken,
            },
            handleUpload: (res) => {
                return res.urls ? res.urls.map(url => ({ src: url, type: 'image' })) : [];
            },
            assets: [],
        },
        canvas: {
     styles: extCss || [],
     scripts: extJs || []
 },
        allowScripts: 1,
    });
    editor.on('load', () => {
     const canvasWindow = editor.Canvas.getWindow();
     if (canvasWindow) {
         const loadEvent = new Event('load');
         canvasWindow.dispatchEvent(loadEvent);
     } else {
         console.warn('Canvas window not accessible');
     }
 });
    editor.on('asset:add', (asset) => {
        console.log('New asset added:', asset);
    });
    const blockManager = editor.BlockManager;
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
        id: 'image',
        label: 'Image',
        media: `<svg style="width:24px;height:24px" viewBox="0 0 24 24">
         <path d="M8.5,13.5L11,16.5L14.5,12L19,18H5M21,19V5C21,3.89 20.1,3 19,3H5A2,2 0 0,0 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19Z" />
     </svg>`,
        content: { type: 'image', src: '', },
        activate: true,
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

    // Add blocks
    if (!markdown) {
    editor.setComponents(`
        <header style="padding: 20px; background: #0073e6; color: white; text-align: center; font-size: 24px; font-weight: bold;">
            My Awesome Website
        </header>
        <nav style="background: #005bb5; padding: 10px; text-align: center;">
            <a href="#" style="color: white; text-decoration: none; margin: 0 15px;">Home</a>
            <a href="#" style="color: white; text-decoration: none; margin: 0 15px;">About</a>
            <a href="#" style="color: white; text-decoration: none; margin: 0 15px;">Contact</a>
        </nav>
        <div style="padding: 40px; text-align: center;">
            <h1 style="color: #333;">Welcome to My Website</h1>
            <p style="color: #666; max-width: 600px; margin: auto;">
                This is your starting template. You can edit any part of this page using the drag-and-drop editor.
            </p>
            <button style="padding: 10px 20px; background: #0073e6; color: white; border: none; margin-top: 20px; cursor: pointer;">
                Get Started
            </button>
        </div>
        <section style="background: #f8f8f8; padding: 40px; text-align: center;">
            <h2 style="color: #333;">Features</h2>
            <p style="color: #666; max-width: 600px; margin: auto;">
                Add custom blocks, styles, and elements to build the perfect page.
            </p>
        </section>
        <footer style="padding: 20px; background: #333; color: white; text-align: center;">
            &copy; 2025 My Company. All rights reserved.
        </footer>
    `);
}

};

const loadPage = async () => {
    try {
        const response = await axios.get(`/api/saas/pages/${route.params.id}`);

        const markdown = response.data.html;
        const inlineCss = response.data.inline_css;
        const externalCss = response.data.external_css;
        const externalScripts = response.data.external_scripts;

        document.getElementById('gjs').innerHTML = markdown
        initializeGrapeJS(externalCss, externalScripts, markdown);
        editor.setStyle(inlineCss);
        editor.render()
        if (!markdown) return;

    } catch (error) {
        console.error('Error loading page:', error);
    }
};

const saveTemplate = async () => {
    if (!route.params.id) return;
    const html = editor.getHtml();
    const css = editor.getCss();
    try {
        await axios.put(`/api/saas/pages/${route.params.id}`, {
            name: page.value,
            html: html,
            css: css,
            status: 'draft',
        });
    } catch (error) {
        console.error('Error saving page:', error);
    }
};

onMounted(async () => {
    await loadPage();
})
</script>

<style scoped>
.builder-container {
    padding: 20px;
}

.save-button {
    margin-top: 10px;
    padding: 10px 20px;
    background: #007bff;
    color: #fff;
    border: none;
    border-radius: 5px;
    cursor: pointer;
}

.save-button:hover {
    background: #0056b3;
}
</style>
