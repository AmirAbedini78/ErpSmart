# 2026-06-25 — Warehouse Media Resourceable Contract Fix

## Context

After adding the Attachments tab to Warehouse, the UI rendered correctly and posted to the correct endpoint:

```text
POST /api/warehouses/13/media
```

The upload reached Core `MediaController`, uploaded the file, and failed during media attach/pivot event handling.

## Observed error

```text
Modules\Warehouse\Models\Warehouse::Modules\Core\Common\Changelog\{closure}(): Argument #1 ($model) must be of type Modules\Core\Models\Model&Modules\Core\Contracts\Resources\Resourceable, Modules\Warehouse\Models\Warehouse given
```

Stack trace showed:

```text
LogsModelPivotChanges.php
pivotAttached
Plank\Mediable\Mediable::attachMedia
MediaController::store
```

## Root cause

Warehouse model used the Resourceable trait but did not implement the Resourceable contract.

Trait present:

```php
use Modules\Core\Resource\Resourceable;
```

Missing contract:

```php
use Modules\Core\Contracts\Resources\Resourceable as ResourceableContract;

class Warehouse extends Model implements ResourceableContract
```

Core changelog pivot listeners require the actual contract, not only the trait.

## Decision

Patch `modules/Warehouse/app/Models/Warehouse.php` so the model explicitly implements `Modules\Core\Contracts\Resources\Resourceable` while keeping the existing `Modules\Core\Resource\Resourceable` trait.

## Validation command

```bash
docker compose exec app php patches/verify_warehouse_media_resourceable_contract_fix.php
```

Expected key output:

```text
model_implements_resourceable_contract : true
model_uses_resourceable_trait          : true
resource_is_mediable                   : true
model_has_media_relation               : true
```

## Impact

- Enables media uploads on Warehouse records.
- Keeps compatibility with Core changelog and pivot event listeners.
- Establishes a rule for future custom ERP modules: Resource models must implement the Resourceable contract whenever Core pivot/timeline/changelog systems are involved.
