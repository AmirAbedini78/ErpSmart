# Warehouse StandardDetailPage Frontend Conversion

Status: applied
Date: 2026-06-29

Phase 2 converted the Warehouse detail frontend to consume backend-driven `StandardDetailPage` metadata.

Context:
- Phase 1 added backend metadata in `modules/Warehouse/app/Resources/Warehouse.php`.
- This phase updated `modules/Warehouse/resources/js/views/WarehousesView.vue`.

What changed:
- Warehouse detail now reads `resourceInformation.value.detailPage`.
- Details and media render from `page.panels` through Core `Panels.vue`.
- Notes and Activities render dynamically from `page.tabs`.
- A local `tabComponents` map resolves notes and activities tab components to avoid global component resolution issues.

Intentionally excluded:
- Timeline tab.
- Documents tab.
- Calls tab.
- Emails/MailClient tab.
- Core or first-party provider changes.

Manual tests still required:
- Open `/warehouses/{id}`.
- Confirm details fields, floating edit refresh, notes, attachments, activities, activity associations, and activity comments.
- Confirm index/import/export remain unaffected.
