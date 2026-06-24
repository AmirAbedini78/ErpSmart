import offlineGlobalVue from './vite-plugins/offline-global-vue.mjs'
import vue from '@vitejs/plugin-vue'
import laravel from 'laravel-vite-plugin'
import unfonts from 'unplugin-fonts/vite'
import { defineConfig } from 'vite'

const moduleAliasRegex = /@\/([a-zA-Z]+)\/(.*)/

export default defineConfig({
  resolve: {
    alias: [
      {
        find: moduleAliasRegex,
        replacement: '/modules/$1/resources/js/$2',
      },
    ],
  },

  server: {
    host: '0.0.0.0',
    port: 5173,
    strictPort: true,
    hmr: {
      host: 'localhost',
      port: 5173,
      protocol: 'ws',
    },
    watch: {
      usePolling: true,
      interval: 1000,
    },
  },

  plugins: [
    laravel({
      input: ['resources/js/app.js', 'resources/css/contentbuilder/theme.css'],
      refresh: true,
    }),

    unfonts({
      custom: {
        families: [
          {
            name: 'Dancing Script',
            local: 'Dancing Script',
            src: './public/fonts/DancingScript-Regular.ttf',
          },
        ],
      },
    }),

    offlineGlobalVue(),

    vue({
      template: {
        transformAssetUrls: {
          base: null,
          includeAbsolute: false,
        },
      },
    }),
  ],
})
