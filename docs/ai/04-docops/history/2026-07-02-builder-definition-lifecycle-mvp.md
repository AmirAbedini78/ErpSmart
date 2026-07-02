# Builder Definition Lifecycle MVP

Date: 2026-07-02

## Summary

Implemented the first safe Builder lifecycle operations for Builder definitions only:

- archive unpublished definitions
- restore archived definitions
- delete unpublished draft/control-plane records

Delete draft removes Builder definition, version, and preview-run database records only. It does not delete runtime modules, generated files, preview artifacts, migrations, database tables, media, or business data.

## Safety Boundary

Publish, uninstall, rollback, runtime module deletion, table drops, and generated capability removal remain forbidden and unimplemented.

## Verification

Added `patches/verify_builder_definition_lifecycle_mvp.php` to check static contracts, UI strings, routes, and runtime smoke for archive/restore/delete-draft cleanup.
