import { copyFileSync, existsSync, mkdirSync, statSync } from 'node:fs'
import { dirname, join, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = dirname(fileURLToPath(import.meta.url))
const root = resolve(__dirname, '..')
const sourceDir = join(root, 'node_modules', 'vue', 'dist')
const targetDir = join(root, 'public', 'vendor', 'vue')

const files = ['vue.global.js', 'vue.global.prod.js']

if (!existsSync(sourceDir)) {
  console.error(`Vue dist directory not found: ${sourceDir}`)
  console.error('Run npm install in the node container before syncing Vue assets.')
  process.exit(1)
}

mkdirSync(targetDir, { recursive: true })

for (const file of files) {
  const source = join(sourceDir, file)
  const target = join(targetDir, file)

  if (!existsSync(source)) {
    console.error(`Required Vue asset not found: ${source}`)
    process.exit(1)
  }

  copyFileSync(source, target)
  const size = statSync(target).size
  console.log(`Synced ${file} -> public/vendor/vue/${file} (${size} bytes)`)
}

console.log('Local Vue assets are ready. The app no longer needs unpkg.com for Vue runtime.')
