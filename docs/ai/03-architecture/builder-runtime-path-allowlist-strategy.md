# ERPSMART Builder Runtime Path Allowlist Strategy

Date: 2026-07-02

Status: planning only. Runtime path writing is not implemented.

## Allowed Future Runtime Path Families

Future generated-module runtime writes may be limited to:

- `modules/{GeneratedModule}/App/Models`
- `modules/{GeneratedModule}/App/Http/Controllers`
- `modules/{GeneratedModule}/App/Http/Resources`
- `modules/{GeneratedModule}/database/migrations` as planned files only, not executed in this phase
- `modules/{GeneratedModule}/resources/js`
- `modules/{GeneratedModule}/routes`
- `docs/ai/generated-manifests` if generated publish manifests need reviewable copies

The `{GeneratedModule}` segment must come from the approved Builder definition and pass module slug/name validation.

## Forbidden Paths

Runtime write planning must forbid:

- `app/Core`
- `modules/Core`
- `modules/SaaS`
- `modules/Updater`
- `modules/Installer`
- `modules/Warehouse` unless it is explicitly the approved target module in a future separate task
- `vendor`
- `node_modules`
- `public/build`
- `.env`
- `composer.json`
- `package.json`
- `routes/web.php`
- `resources/js/app.js`
- global `database/migrations` root unless explicitly approved by a future migration strategy
- any path containing `..`
- any absolute path outside the project

## Validation Requirements

Every `future_runtime_path` must be normalized, checked for traversal, checked against the allowlist, checked against forbidden path families, and tied to the approved definition checksum. The path validator must reject symlink escapes and ambiguous case/path variants.

## Current Boundary

This strategy is documentation only. No runtime path write implementation exists.
