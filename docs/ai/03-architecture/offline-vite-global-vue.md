# Offline Vite Global Vue Plugin

## Context

`@concordcrm/vite-plugin-global-vue` fetches `https://unpkg.com/vue@{version}/dist/vue.global.js` during `vite build` to generate `.vue.alias.js`. This means `npm run build` fails when internet access is unavailable.

Observed error:

```text
[vite-plugin-global-vue] fetch failed
ENOENT: no such file or directory, unlink '/var/www/html/.vue.alias.js'
```

## ERPSMART rule

Do not use remote fetch during build for core runtime dependencies.

## Implementation

`vite.config.js` uses:

```js
import offlineGlobalVue from './vite-plugins/offline-global-vue.mjs'
```

The local plugin reads:

```text
node_modules/vue/dist/vue.global.js
```

and generates `.vue.alias.js` locally, then safely removes the temp file only if it exists.

## Validation

Run build with internet disconnected:

```bash
docker compose exec node npm run build
```

Expected: build completes without `fetch failed` and without `unpkg.com` access.
