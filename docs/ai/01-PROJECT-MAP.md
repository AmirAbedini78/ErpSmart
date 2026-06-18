# 01 — ErpSmart Project Map

> هدف این سند: نقشهٔ بالا-سطحی پروژه برای توسعه با AI/RAG. جزئیات هر دامنه در `02-domains/` نگهداری می‌شود.

## Snapshot
- Backend: Laravel 11
- Frontend: Vue 3 + Vite
- Module system: `nwidart/laravel-modules`
- Auth/API style: Laravel Sanctum + same-origin API
- Core UI pattern: Resource / Field / Action / Workflow، نزدیک به مفهوم Nova Resource، ولی با پیاده‌سازی اختصاصی Concord/ErpSmart.
- Important runtime paths:
  - `modules/*/module.json`
  - `modules/*/app/Providers/*ServiceProvider.php`
  - `modules/*/routes/api.php`, `routes/web.php`
  - `modules/*/app/Resources/*.php`
  - `modules/*/app/Models/*.php`
  - `modules/*/resources/js/app.js`
  - `resources/js/app.js`
  - `vite.config.js`

## Boot / Lifecycle
1. Laravel boots `app/Providers` and module providers.
2. Module status is read from `modules_statuses.json` and cached in `bootstrap/cache/modules.php`.
3. Each enabled module registers:
   - config / routes / translations / migrations / views
   - backend resources, workflow triggers/actions, menus/settings
   - frontend entry `modules/<Module>/resources/js/app.js`
4. Root frontend `resources/js/app.js` imports all module app files and exposes `window.CreateApplication`.
5. Blade renders app shell, Vite/build assets mount Vue app.

## Module Inventory
| Module | Priority | Resources | JS app | Routes |
|---|---:|---:|---|---|
| Activities | 0 | 2 | yes | 2 |
| Auth | 0 | 0 | yes | 1 |
| Billable | 0 | 1 | yes | 1 |
| Brands | 0 | 0 | yes | 1 |
| Calls | 0 | 2 | yes | 2 |
| Comments | 0 | 0 | yes | 1 |
| Contacts | 0 | 5 | yes | 2 |
| Core | 10000 | 0 | yes | 2 |
| Deals | 0 | 6 | yes | 2 |
| Documents | 0 | 3 | yes | 2 |
| Installer | 0 | 0 | yes | 1 |
| MailClient | 0 | 3 | yes | 3 |
| Notes | 0 | 1 | yes | 1 |
| ThemeStyle | 0 | 0 | yes | 1 |
| Translator | 0 | 0 | yes | 1 |
| Updater | 0 | 0 | yes | 2 |
| Users | 0 | 1 | yes | 3 |
| WebForms | 0 | 0 | yes | 2 |
| Warehouse | 0 | 1 | yes | 2 |

## Known local-development traps
- `public/hot` means Laravel uses Vite dev server. If node is stopped, remove `public/hot` and run `npm run build`.
- New entity frontend routes must be imported into `resources/js/app.js` unless the module has a separately built manifest loaded via `Innoclapps::viteOutput()`. Missing this import causes SPA 404 even when the backend Resource exists.
- `bootstrap/cache/*.php` can keep old enabled modules. If module boot breaks, delete module/config cache manually.
- `storage`, `bootstrap/cache`, and `public` must be writable by container web user.
- Saas module currently performs tenant schema mutation on boot; exclude `telescope_*` and `pulse_*` from tenant migration before enabling.
- Workflows depend on seed data such as `activity_types.flag = task`.

## RAG strategy
Small local models must not ingest the whole repository blindly. Always resolve:
1. module doc from `02-domains/INDEX.yml`
2. dependencies in frontmatter
3. key files in `touches_code`
4. validation queries
5. only then inspect additional references.
