# Warehouse StandardDetailPage Verifier

Status: verifier added
Date: 2026-06-29

Added a read-only verifier before converting Warehouse detail to the first-party `StandardDetailPage` pattern.

Created:
- `patches/verify_warehouse_standard_detail_page_contract.php`

Purpose:
- Protect current Warehouse detail behavior before refactor.
- Confirm Warehouse backend Resource, JSON Resource, model traits, hard-coded current tabs, media panel, floating edit, and `useResource` contracts.
- Confirm target first-party evidence exists in Core, Contact, Company, Deal, Activities, and Notes.
- Fail on unsafe changed paths such as Core, vendor, migrations, and package/composer manifests.

Notes:
- The verifier marks future StandardDetailPage readiness checks as `TARGET`, not required Warehouse failures.
- The verifier does not implement the Warehouse StandardDetailPage conversion.
- Warehouse runtime behavior was not changed.
