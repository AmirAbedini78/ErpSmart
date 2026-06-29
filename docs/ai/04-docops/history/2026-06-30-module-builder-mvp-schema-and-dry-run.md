# Module Builder MVP Schema And Dry-Run Foundation

Status: applied
Date: 2026-06-30

Created the first formal Module Builder MVP definition schema and dry-run design foundation.

Added:
- `docs/ai/03-architecture/module-builder-mvp-schema.md`
- `docs/ai/05-rag/contracts/module-builder-mvp-schema.json`
- `patches/verify_module_builder_mvp_schema.php`

The schema is based on the current Warehouse canonical template and covers module identity, Resource metadata, fields, validation rules, field visibility, table/index behavior, StandardDetailPage panels/tabs, capability flags, permissions, frontend files, and verifier generation.

The dry-run command shape is documented as:

```bash
php artisan erpsmart:make-module --definition=module-definition.json --dry-run
```

No Artisan command was implemented in this phase. Command registration is deferred to avoid runtime/Core changes before the schema and dry-run output contract are verified.

No generated module files are created by this task.
