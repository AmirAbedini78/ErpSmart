import fs from 'node:fs'
import path from 'node:path'

const exportMatchRegex = /exports\.(\w+)/gm
const vueAliasTmpFileName = '.vue.alias.js'

export default function offlineGlobalVue() {
  let vueAliasTmpPath = ''
  let root = ''

  return {
    name: 'erpsmart-offline-global-vue',

    config: () => ({
      resolve: {
        alias: [
          {
            find: 'vue',
            customResolver: () => vueAliasTmpPath,
          },
        ],
      },
    }),

    configResolved(resolvedConfig) {
      root = resolvedConfig.root
      vueAliasTmpPath = path.join(root, vueAliasTmpFileName)
    },

    buildStart() {
      const vueGlobalPath = path.join(root, 'node_modules', 'vue', 'dist', 'vue.global.js')

      if (!fs.existsSync(vueGlobalPath)) {
        throw new Error(
          `[erpsmart-offline-global-vue] Missing local Vue global runtime: ${vueGlobalPath}. Run npm install inside the node container.`,
        )
      }

      const src = fs.readFileSync(vueGlobalPath, 'utf8')
      const uniqueExports = new Set()
      let content = ''
      let match

      while ((match = exportMatchRegex.exec(src)) !== null) {
        uniqueExports.add(match[1])
      }

      if (uniqueExports.size === 0) {
        throw new Error('[erpsmart-offline-global-vue] Could not detect Vue exports from local vue.global.js.')
      }

      uniqueExports.forEach(name => {
        content += `export const ${name} = Vue.${name};\n`
      })

      fs.writeFileSync(vueAliasTmpPath, content)
    },

    buildEnd() {
      if (vueAliasTmpPath && fs.existsSync(vueAliasTmpPath)) {
        fs.unlinkSync(vueAliasTmpPath)
      }
    },
  }
}
