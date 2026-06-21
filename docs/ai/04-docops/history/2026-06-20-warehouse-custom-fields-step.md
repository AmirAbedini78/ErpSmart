# 2026-06-20 — Warehouse Custom Fields and Language Stabilization

## Context

After Warehouse create/edit/table/permissions were confirmed, the next target was Custom Fields compatibility. The user also reported that some newly-added language entries sometimes render as JSON objects instead of human-readable labels.

## Changes

```text
modules/Warehouse/resources/js/views/WarehousesIndex.vue
modules/Warehouse/app/Resources/Warehouse.php
modules/Warehouse/lang/en/warehouse.php
docs/ai/03-architecture/resource-custom-fields.md
docs/ai/05-rag/module-manifest/warehouse.json
```

## Functional change

The Warehouse table action menu now has a super-admin-only shortcut:

```text
Customize fields -> /settings/fields/warehouses
```

This routes Warehouse to the Core Settings Fields UI instead of creating a custom module-specific custom field screen.

## Language fix

Warehouse permission/action labels now use leaf translation keys. The Resource permission UI definitions for bulk delete, export, and import include explicit `as` labels to reduce object/array translation rendering risks.

## Validation checklist

```text
1. Run optimize:clear and npm run build.
2. Login as super admin.
3. Open /warehouses.
4. Open the dropdown menu and click Customize fields.
5. Confirm /settings/fields/warehouses opens.
6. Add a Warehouse custom field.
7. Enable it on create/update/detail views.
8. Create/edit a Warehouse and verify the custom field persists.
9. Check role permissions screen no longer displays JSON-like labels for Warehouse actions.
```

## Builder/RAG note

Custom fields are not just a UI feature. For generated modules, the Builder must align Resource contracts, frontend settings route access, language leaf keys, and SmartDocs manifest entries.
