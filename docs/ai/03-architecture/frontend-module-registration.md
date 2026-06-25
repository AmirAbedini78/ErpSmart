# Frontend Module Registration Pattern

## Purpose
This document records how a module becomes visible to the Vue SPA. It exists because Warehouse initially passed backend checks but still returned a frontend 404.

## Rule
A module is not complete when only backend Laravel files exist. It must also be registered in the frontend bundle.

## Current pattern
The root frontend entry is:

```text
resources/js/app.js
```

Existing modules are imported there:

```js
import '@/Core/app.js'
import '@/Auth/app.js'
import '@/Users/app.js'
...
```

Warehouse must also be imported:

```js
import '@/Warehouse/app.js'
```

Then the module entry registers routes:

```text
modules/Warehouse/resources/js/app.js
```

```js
import routes from './routes'

if (window.Innoclapps) {
  Innoclapps.booting((app, router) => {
    routes.forEach(route => router.addRoute(route))
  })
}
```

## Builder requirement
The future Module Builder must create frontend registration automatically. Otherwise the backend Resource can exist but the UI route will still fail.

Generated module frontend requirements:

```text
modules/<Module>/resources/js/app.js
modules/<Module>/resources/js/routes.js
modules/<Module>/resources/js/views/<Entity>Index.vue
modules/<Module>/resources/js/views/<Entity>Create.vue
modules/<Module>/resources/js/views/<Entity>Edit.vue
modules/<Module>/resources/js/views/<Entity>View.vue
resources/js/app.js import OR a future dynamic manifest/import mechanism
```

## Validation commands

```bash
docker compose exec app sh -c "grep -n \"<Module>/app\" resources/js/app.js"
sudo rm -f public/hot
docker compose exec node npm run build
docker compose restart app nginx
```

## Known trap
If `public/hot` exists, Laravel will try to use the Vite dev server. For offline or stable Docker testing, remove `public/hot` and use the built assets in `public/build`.
