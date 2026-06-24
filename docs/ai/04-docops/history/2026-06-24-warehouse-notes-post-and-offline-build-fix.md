# 2026-06-24 - Warehouse Notes POST and offline build fix

## Context

After fixing the Notes path contract, Warehouse detail loaded correctly and Notes GET no longer requested `/api/undefined/notes`.

Creating a note still failed with:

```text
POST /api/notes?via_resource=warehouses&via_resource_id=13
422 The selected via resource is invalid.
```

Separately, `npm run build` still depended on internet because the upstream global Vue Vite plugin fetched Vue from `unpkg.com` during build.

## Changes

- Added `AssociatesResources` to the Warehouse resource.
- Added a Warehouse provider hook for `http.request.create_resource_request.notes.rules` so Core Notes accepts `via_resource=warehouses`.
- Replaced `@concordcrm/vite-plugin-global-vue` usage with a local `vite-plugins/offline-global-vue.mjs` implementation.
- The local Vite plugin reads `node_modules/vue/dist/vue.global.js` and does not fetch remote CDN assets.

## Validation

1. Clear Laravel optimized files and cache.
2. Build frontend with internet disconnected.
3. Open Warehouse detail.
4. Add a note with non-empty body.
5. Confirm POST returns 201/200 and the note persists after refresh.

## Builder rule

Notes-enabled generated modules must include both association support and create-request validation. Offline runtime is not enough; build-time plugins must also be offline-safe.
