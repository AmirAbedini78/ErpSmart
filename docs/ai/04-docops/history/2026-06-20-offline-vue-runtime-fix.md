# 2026-06-20 - Offline Vue Runtime Fix

## Context

After Warehouse permission/action cleanup, the UI showed a white page. Browser console showed:

```text
GET https://unpkg.com/vue@3.5.12/dist/vue.global.prod.js net::ERR_CONNECTION_RESET
Vue is not defined
CreateApplication is not defined
```

## Root Cause

The app bundle depends on global `Vue`, but `Modules\Core\Application::vueSrc()` pointed to `unpkg.com`. In restricted/offline environments, the Vue runtime was not loaded.

## Fix

- Changed `Application::vueSrc()` to local `public/vendor/vue` assets.
- Added `scripts/sync-local-vue-assets.mjs` to copy Vue runtime files from `node_modules/vue/dist`.
- Added architecture documentation for offline frontend assets.

## Required Command

```bash
docker compose exec node node scripts/sync-local-vue-assets.mjs
```

Then clear Laravel/Vite state and reload.

## Builder Implication

SmartDocs must track CDN/runtime dependencies. A module may be correct while the platform runtime fails. RAG should distinguish module bugs from platform asset bugs.
