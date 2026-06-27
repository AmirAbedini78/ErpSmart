# RAG Pattern — Warehouse Media Persistence

## Symptom

A file uploads successfully in `ResourceMediaPanel`, appears in the Attachments tab, but disappears after navigating away and returning to the record page.

## Cause

The media pivot exists, but the fresh record payload does not include the `media` relation. The custom page normalizes a missing relation to `[]`.

## Stable fix for Warehouse MVP

Eager-load `media` on the Warehouse model:

```php
protected $with = [
    'media',
];
```

## Future optimization

For high-volume production data, prefer detail-only eager loading or a dedicated API resource response instead of model-level eager loading.
