<?php

declare(strict_types=1);

$root = dirname(__DIR__);

function path_join(string ...$parts): string
{
    return preg_replace('#/+#', '/', implode('/', $parts));
}

function read_file_or_fail(string $path): string
{
    if (! is_file($path)) {
        fwrite(STDERR, "Missing file: {$path}\n");
        exit(1);
    }

    $contents = file_get_contents($path);

    if ($contents === false) {
        fwrite(STDERR, "Cannot read file: {$path}\n");
        exit(1);
    }

    return $contents;
}

function write_file_or_fail(string $path, string $contents): void
{
    $dir = dirname($path);

    if (! is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    if (file_put_contents($path, $contents) === false) {
        fwrite(STDERR, "Cannot write file: {$path}\n");
        exit(1);
    }
}

$viewPath = path_join($root, 'modules/Warehouse/resources/js/views/WarehousesView.vue');
$view = read_file_or_fail($viewPath);

// Clean a small UI typo introduced during previous patch attempts.
$view = str_replace('sm:flex-rowsm:items-start', 'sm:flex-row sm:items-start', $view);
$view = str_replace("\n<ICard class=\"mb-6\">", "\n      <ICard class=\"mb-6\">", $view);

// Ensure the Core global event listener is imported.
if (! str_contains($view, "@/Core/composables/useGlobalEventListener")) {
    $floatingImport = "import { useFloatingResourceModal } from '@/Core/composables/useFloatingResourceModal'";

    if (str_contains($view, $floatingImport)) {
        $view = str_replace(
            $floatingImport,
            $floatingImport."\nimport { onGlobal } from '@/Core/composables/useGlobalEventListener'",
            $view
        );
    } else {
        $resourceFieldsImport = "import { useResourceFields } from '@/Core/composables/useResourceFields'";
        $view = str_replace(
            $resourceFieldsImport,
            $resourceFieldsImport."\nimport { onGlobal } from '@/Core/composables/useGlobalEventListener'",
            $view
        );
    }
}

$syncBlock = <<<'VUE'

function isSameWarehouseFloatingUpdate(payload = {}) {
  return (
    payload.resourceName === resourceName &&
    String(payload.resourceId) === String(warehouseId.value)
  )
}

async function refreshWarehouseDetailAfterFloatingUpdate(payload = {}) {
  if (!isSameWarehouseFloatingUpdate(payload)) {
    return
  }

  if (payload.resource) {
    synchronizeResource(normalizeResource(payload.resource), true)
    setResource(resource)
  }

  try {
    await fetchResource()
    normalizeCurrentResource()
    setResource(resource)
  } catch (error) {
    console.error('Failed to refresh warehouse detail after floating edit.', error)
  }
}

onGlobal('floating-resource-updated', refreshWarehouseDetailAfterFloatingUpdate)
VUE;

if (! str_contains($view, "refreshWarehouseDetailAfterFloatingUpdate")) {
    $pattern = <<<'VUE'
function openEditFloatingModal() {
  floatResourceInEditMode({
    resourceName,
    resourceId: Number(warehouseId.value) || warehouseId.value,
  })
}
VUE;

    if (! str_contains($view, $pattern)) {
        fwrite(STDERR, "Could not locate openEditFloatingModal block in WarehousesView.vue.\n");
        exit(1);
    }

    $view = str_replace($pattern, $pattern.$syncBlock, $view);
}

write_file_or_fail($viewPath, $view);

$architectureDoc = <<<'MD'
# Module Builder Edit Contract

Status: canonical after Warehouse validation.
Updated: 2026-06-28

## Canonical pattern for CRM-style resources

Warehouse now follows the same edit contract used by first-party CRM resources such as Contacts, Companies, and Deals:

1. The detail page is a standalone record view.
2. The Edit action opens the Core floating resource modal.
3. PHP Resource actions expose `Action::make()->floatResourceInEditMode()`.
4. The Vue detail page calls `useFloatingResourceModal().floatResourceInEditMode(...)`.
5. The floating modal component must match the Core contract:
   - `visible`
   - `floatingReady`
   - `resource`
   - `fields`
   - `mode`
   - `updateHandler`
6. After a successful floating edit, the detail page must listen to `floating-resource-updated` and synchronize/fetch the current record without a browser refresh.

## Canonical Warehouse behavior

- Detail route: `/warehouses/:id`
- Edit behavior: Core floating modal, not a separate edit route from detail.
- Update synchronization: `WarehousesView.vue` listens for `floating-resource-updated`, checks the resource name/id, syncs the returned resource, and fetches the latest detail record.

## Superseded attempts

The following approaches were attempted during debugging and must not be used by the module builder:

- Mounting `WarehousesEdit.vue` directly inside the detail page.
- Teleporting the edit view manually to `body`.
- Navigating with `window.location` or a hard edit route from detail.
- Treating `WarehouseFloatingModal` as if Core passes `resourceId` directly. Core passes `resource`, `fields`, `mode`, and `updateHandler` instead.

These are kept only as debugging history in `docs/ai/04-docops/history`. The canonical contract above is the source of truth for future modules.
MD;
write_file_or_fail(path_join($root, 'docs/ai/03-architecture/module-builder-edit-contract.md'), $architectureDoc);

$historyDoc = <<<'MD'
# Warehouse Detail Sync After Floating Edit

Date: 2026-06-28
Status: applied

## Problem

After the Warehouse record was edited through the Core floating modal, the modal saved correctly, but the Warehouse detail page still showed stale data until a manual browser refresh.

## Cause

The Core floating modal emits the global event `floating-resource-updated` after a successful update. The Warehouse detail view was not listening to this event, so the local `useResource` detail state did not synchronize after the modal save.

## Fix

`modules/Warehouse/resources/js/views/WarehousesView.vue` now listens to `floating-resource-updated`, checks that the updated resource is the same Warehouse record, synchronizes the returned resource, then fetches and normalizes the latest detail record.

## Builder lesson

Any CRM-style detail page using Core floating edit must implement post-update detail synchronization. This should be part of the reusable module builder checklist.
MD;
write_file_or_fail(path_join($root, 'docs/ai/04-docops/history/2026-06-28-warehouse-detail-sync-after-floating-edit.md'), $historyDoc);

$supersededDoc = <<<'MD'
# Warehouse Edit Attempt Cleanup

Date: 2026-06-28
Status: documentation cleanup

## Summary

Several zip patches were generated while debugging the Warehouse edit behavior. Some represented failed or temporary approaches. They are not the canonical architecture.

## Canonical result

Use the first-party Core floating resource modal contract:

- `Action::make()->floatResourceInEditMode()` in the PHP Resource.
- `useFloatingResourceModal().floatResourceInEditMode(...)` in the detail view.
- A `WarehouseFloatingModal` component that accepts Core props: `visible`, `floatingReady`, `resource`, `fields`, `mode`, `updateHandler`.
- A `floating-resource-updated` listener in the detail view to refresh detail state without a full browser reload.

## Superseded / do not use

- Inline mounting `WarehousesEdit.vue` inside `WarehousesView.vue`.
- Manual `Teleport` for edit from the detail page.
- Hard route/window-location edit navigation from detail.
- Assuming Core passes `resourceId` into the floating component.

These docs remain as chronological history only. Future RAG/module-builder logic should rely on the canonical edit contract doc.
MD;
write_file_or_fail(path_join($root, 'docs/ai/04-docops/history/2026-06-28-warehouse-edit-attempts-superseded.md'), $supersededDoc);

$ragDoc = <<<'JSON'
{
  "module": "warehouse",
  "feature": "floating_edit_modal",
  "status": "canonical",
  "updated_at": "2026-06-28",
  "contract": {
    "detail_route": "/warehouses/:id",
    "edit_trigger": "useFloatingResourceModal().floatResourceInEditMode",
    "php_resource_action": "Action::make()->floatResourceInEditMode()",
    "floating_component": "WarehouseFloatingModal",
    "core_props": [
      "visible",
      "floatingReady",
      "resource",
      "fields",
      "mode",
      "updateHandler"
    ],
    "post_update_sync_event": "floating-resource-updated"
  },
  "do_not_use": [
    "detail_inline_edit_mount",
    "manual_teleport_edit_modal",
    "window_location_hard_edit_route",
    "floating_modal_resource_id_prop_contract"
  ]
}
JSON;
write_file_or_fail(path_join($root, 'docs/ai/05-rag/module-manifest/warehouse-edit-contract.json'), $ragDoc);

echo "Warehouse detail sync after floating edit applied.\n";
