# Builder Publish Dry-Run File Generator

Status: implemented MVP
Date: 2026-07-02

## Purpose

The publish dry-run generator creates a sandboxed set of representative future publish files for review, AI/RAG analysis, and future approval. It is not publish.

Dry-run artifacts are generated only under:

`storage/app/builder-publish-dry-runs/{definition_id}/{run_id}/`

## Preview vs Readiness vs Dry Run vs Future Publish

Preview renders module-like preview output under `storage/app/module-builder-preview`.

Publish readiness analyzes conflicts and writes a diagnostic readiness artifact under `storage/app/builder-publish-readiness`.

Publish dry-run renders representative future publish files under `storage/app/builder-publish-dry-runs`.

Future publish will be a separate safety-critical workflow that may write runtime module files, run migrations, register routes/menus/permissions, and require rollback. That is intentionally absent here.

## Safety Boundaries

The dry-run generator must not:

- write to `modules/`
- write to `database/migrations/`
- write to application `routes/`
- write to `resources/js/app.js`
- run migrations
- register runtime routes
- create real modules
- create preview runs
- create Builder versions
- change Builder definition status
- modify Core, Warehouse, SaaS, licensing, updater, installer, vendor, node_modules, or public build assets

Runtime safety flags remain:

- `writes_performed = 0`
- `runtime_writes_performed = 0`
- `publish_executed = false`
- `runtime_module_effect = none`

## Folder Structure

The dry-run folder contains:

- `README.md`
- `definition.json`
- `publish-readiness-report.json`
- `future-file-plan.json`
- `backend/*.stub`
- `frontend/*.stub`
- `manifest/publish-dry-run-manifest.json`

Every generated representative file must clearly state:

- `DRY RUN ONLY - NOT RUNTIME CODE`
- `Generated under storage for review only`
- `Do not copy to production without publish pipeline`

## AI/RAG Use

AI Builder Agent may call the dry-run generator and read generated dry-run artifacts. It must not copy dry-run files into runtime paths, treat dry-run files as source of truth, or execute publish.

## Known Limitations

The generated files are representative and not production-perfect. They are designed for review and planning before future publish pipeline implementation.

## Next Steps

- add richer templates
- add generated verifier dry-run stubs
- add publish manifest planning
- add approval workflow
- keep real publish as a separate safety-critical batch
