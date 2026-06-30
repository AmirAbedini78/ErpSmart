# Module Builder Dry-Run Command

Status: applied
Date: 2026-06-30

Added the first dry-run-only Module Builder command:

```bash
php artisan erpsmart:make-module --definition=docs/ai/05-rag/examples/warehouse-like-module-definition.json --dry-run
```

The command is registered safely by placing it in `app/Console/Commands`, which is already loaded by `app/Console/Kernel.php`.

The command:
- requires `--definition`
- refuses to run without `--dry-run`
- loads and validates the JSON definition at a basic structural level
- prints normalized module/entity/resource/table names
- prints selected capabilities
- prints backend, frontend, and docs/verifier file plans
- prints unsupported/out-of-scope warnings
- prints `Writes performed: 0`
- does not create generated module files

Also added:
- `docs/ai/05-rag/examples/warehouse-like-module-definition.json`
- `patches/verify_module_builder_dry_run_command.php`

No Warehouse runtime source, Core source, migrations, package manifests, vendor files, build files, or generated module files were changed.
