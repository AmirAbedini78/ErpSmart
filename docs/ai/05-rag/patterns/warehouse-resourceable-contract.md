# RAG Pattern — Warehouse Resourceable Contract Requirement

## Problem

A custom Resource model can use the `Modules\Core\Resource\Resourceable` trait and still fail Core type checks if it does not implement `Modules\Core\Contracts\Resources\Resourceable`.

## Trigger

This appears when Core pivot event systems run, especially media attachments and changelog listeners.

## Error signature

```text
Argument #1 ($model) must be of type Modules\Core\Models\Model&Modules\Core\Contracts\Resources\Resourceable, Modules\Warehouse\Models\Warehouse given
```

## Fix pattern

```php
use Modules\Core\Contracts\Resources\Resourceable as ResourceableContract;
use Modules\Core\Resource\Resourceable;

class Warehouse extends Model implements ResourceableContract
{
    use Resourceable;
}
```

When Media is used:

```php
use Modules\Core\Common\Media\HasMedia;

class Warehouse extends Model implements ResourceableContract
{
    use HasMedia,
        Resourceable;
}
```

## Applicability

Use this pattern for every ERP module model that participates in:

- Resource engine
- Timeline
- Changelog
- Notes
- Media / Attachments
- Pivot associations
- Generic Core record tabs

## Do not confuse

- `Modules\Core\Resource\Resourceable` is a trait.
- `Modules\Core\Contracts\Resources\Resourceable` is a contract/interface.
- Core listeners may require the interface even when trait methods exist.
