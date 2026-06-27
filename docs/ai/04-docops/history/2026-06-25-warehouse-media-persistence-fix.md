# 2026-06-25 — Warehouse Media Persistence Fix

## Problem

After the ResourceMediaPanel upload endpoint succeeded and the file appeared in the UI, navigating away and returning to the Warehouse detail page showed an empty Attachments tab.

## Diagnosis

The file was persisted in Core media tables and attached to `mediables`, but the Warehouse detail record was fetched again without the `media` relation. The custom Warehouse detail page normalizes missing `media` to an empty array, causing the attachments list to look empty after reload.

## Fix

Added eager loading of the `media` relation to `Modules\Warehouse\Models\Warehouse`.

```php
protected $with = [
    'media',
];
```

## RAG notes

For future ERP modules that use Core `ResourceMediaPanel`, remember:

- Upload success does not guarantee reload visibility.
- The detail record response must include `media`.
- If the module uses a custom detail view, do not assume Core generic record renderer will inject all panel context and loaded relations.
- Use `verify_warehouse_media_persistence_fix.php` as the first persistence check.
