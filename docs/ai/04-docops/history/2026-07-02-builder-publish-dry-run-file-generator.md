# Builder Publish Dry-Run File Generator

Date: 2026-07-02

## Summary

Added a sandboxed publish dry-run file generator for Builder definitions.

Dry-run artifacts are written only under `storage/app/builder-publish-dry-runs/{definition_id}/{run_id}/`.

## Safety Boundary

This is not publish. It does not write runtime modules, database migrations, routes, public build assets, preview runs, Builder versions, or real module directories.

Runtime write flags:

- `writes_performed = 0`
- `runtime_writes_performed = 0`
- `publish_executed = false`
- `runtime_module_effect = none`
