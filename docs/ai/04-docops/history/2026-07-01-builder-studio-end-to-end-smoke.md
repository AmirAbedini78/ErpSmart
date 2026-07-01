# Builder Studio End-to-End Smoke

Date: 2026-07-01

## Summary

Added the final Builder Studio demo-path smoke and response hardening before Visual Form Layout Builder work.

## Backend

- Validation responses now include `validation_report` while keeping the existing `report` alias.
- Preview responses now include `validation_report` and `output_text`.
- Invalid preview responses include the current definition plus validation report details.

## UI

- Builder definition detail view now displays API failure alerts for save, validate, and preview.
- Preview failure responses keep validation reports visible when available.

## RAG

- Added Builder Studio smoke contract.
- Updated Builder Studio AI/RAG manifest, API map, and Agent safety boundaries with the end-to-end smoke path, cleanup rules, preview output safety check, and no-publish/no-migration/no-runtime-write boundaries.

## Safety

- Publish remains absent.
- No write-capable runtime generation was added.
- No Core, Warehouse, SaaS, updater, installer, migration, package, composer, vendor, node_modules, or public build files were changed.
