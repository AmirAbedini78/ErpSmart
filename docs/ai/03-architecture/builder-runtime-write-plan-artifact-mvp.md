# Builder Runtime Write Plan Artifact MVP

The runtime write plan artifact is a control-plane-only step between staged file validation and any future runtime write implementation. It maps validated staged artifacts to future runtime paths, applies the runtime path allowlist, records checksums, classifies planned write actions, and writes a reviewable JSON report under storage.

This is not runtime write and it is not publish. The service does not copy staged files, write module files, run migrations, register routes, mark a module as published, or execute rollback.

## Flow

1. Load an existing publish execution record.
2. Require execution status `staging_validated`.
3. Read the staged file validation report from `storage/app/builder-publish-staged-validations`.
4. Read the staging root and rollback manifest draft under `storage/app/builder-publish-*`.
5. Map staged files to future runtime paths.
6. Validate every future path against the allowlist and forbidden path policy.
7. Classify each planned write as `create`, `overwrite`, `skip`, or `planned_migration`.
8. Mark overwrites as requiring a future backup before any runtime write.
9. Mark migrations as planned only; they are not executed in this phase.
10. Write the report to `storage/app/builder-runtime-write-plans/{definition_id}/{execution_id}/runtime-write-plan.json`.
11. Update the rollback manifest draft with planned write entries only.
12. Store the report path and report copy on the execution metadata.

## Safety Boundaries

Allowed write scope:

- `storage/app/builder-runtime-write-plans/{definition_id}/{execution_id}`
- The existing rollback manifest draft for the same execution, with planned write entries only

Forbidden effects:

- No writes to `modules/`
- No writes to global `database/migrations/`
- No route registration
- No generated migration execution
- No copy to runtime
- No publish status
- No rollback execution

## Allowlist And Backup Planning

Future runtime paths are limited to generated module families such as models, controllers, resources, module migrations as planned files, module JavaScript resources, module routes, and generated AI manifests. Existing target files are detected with a read-only check and marked as `overwrite`; every overwrite requires a future backup before runtime write.

Forbidden paths include Core, Warehouse, SaaS, Updater, Installer, vendor, node_modules, public build assets, `.env`, package files, app entrypoints, global route files, and global migration roots.

## AI/RAG Notes

AI Builder Agent may summarize this plan when explicitly asked or when a human starts the control-plane action. It must not execute runtime writes, copy artifacts, bypass the allowlist, run migrations, or treat the report as published code.
