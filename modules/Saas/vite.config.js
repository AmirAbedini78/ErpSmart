import concord from '@concordcrm/vite-plugin-module'
import vue from '@vitejs/plugin-vue'
import dotenv from 'dotenv'
import dotenvExpand from 'dotenv-expand'
import laravel from 'laravel-vite-plugin'
import path from 'path'
import { fileURLToPath } from 'url'
import { defineConfig } from 'vite'

const __filename = fileURLToPath(import.meta.url)
const __dirname = path.dirname(__filename)

dotenvExpand.expand(dotenv.config({ path: '../../.env' /*, debug: true*/ }))

export default defineConfig({
  build: {
    outDir: '../../public/modules/saas/build',
    emptyOutDir: true,
  },
  plugins: [
    laravel({
      publicDirectory: '../../public',
      buildDirectory: 'modules/saas/build',
      hotFile: '../../storage/hot',
      input: [
        // __dirname + '/resources/css/app.css',
        __dirname + '/resources/js/app.js',
      ],
      refresh: true,
    }),
    vue({
      template: {
        transformAssetUrls: {
          base: null,
          includeAbsolute: false,
        },
      },
    }),
    concord('saas', __dirname),
  ],
})
