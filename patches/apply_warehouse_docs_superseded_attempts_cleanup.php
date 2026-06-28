<?php

declare(strict_types=1);

$root = getcwd();

function project_path(string $relative): string
{
    global $root;

    return rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.ltrim($relative, DIRECTORY_SEPARATOR);
}

function ensure_dir(string $path): void
{
    if (! is_dir($path)) {
        mkdir($path, 0775, true);
    }
}

function read_file(string $relative): string
{
    $path = project_path($relative);

    return is_file($path) ? file_get_contents($path) : '';
}

function write_file(string $relative, string $contents): void
{
    $path = project_path($relative);
    ensure_dir(dirname($path));
    file_put_contents($path, $contents);
}

function append_once(string $relative, string $marker, string $content): void
{
    $current = read_file($relative);

    if (str_contains($current, $marker)) {
        return;
    }

    $separator = $current !== '' && ! str_ends_with($current, "\n") ? "\n\n" : "\n";
    write_file($relative, $current.$separator.$content);
}

$canonicalDoc = 'docs/ai/03-architecture/module-builder-edit-contract.md';
$supersededMarker = '<!-- WAREHOUSE_EDIT_SUPERSEDED_ATTEMPTS_CANONICAL -->';
$supersededSection = <<<MD

{$supersededMarker}

## Superseded Warehouse edit attempts — DO NOT USE

These approaches were tried during debugging and are intentionally superseded by the canonical Core Floating Resource Modal contract above. They must not be used by the module builder, RAG retrieval, or future Warehouse-like modules.

- `detail_inline_edit`: mounting `WarehousesEdit.vue` directly inside `WarehousesView.vue`.
- `teleport_inline_edit`: Teleporting `WarehousesEdit.vue` to `body` from the detail page.
- `hard_edit_route`: navigating detail Edit to `/warehouses/{id}/edit` or using `window.location`.
- `resource_id_floating_modal_contract`: custom floating modal expecting `resourceId` and fetching its own data.

Canonical approach:

- Add `Action::make()->floatResourceInEditMode()` in the PHP Resource actions.
- Open edit via `useFloatingResourceModal().floatResourceInEditMode({ resourceName, resourceId })`.
- Register `{SingularName}FloatingModal` globally, for Warehouse: `WarehouseFloatingModal`.
- The floating modal must accept the Core props: `visible`, `floatingReady`, `resource`, `fields`, `mode`, and `updateHandler`.
- Detail pages must listen to `floating-resource-updated` and refresh/synchronize the current record without a browser refresh.

MD;
append_once($canonicalDoc, $supersededMarker, $supersededSection);

$supersededDoc = 'docs/ai/04-docops/superseded/warehouse-edit-attempts.md';
write_file($supersededDoc, <<<MD
# Warehouse edit attempts — superseded / do not use

Status: superseded
Canonical replacement: Core Floating Resource Modal contract
Last updated: 2026-06-28

## Do not use these patterns

1. Direct inline mount of `WarehousesEdit.vue` inside `WarehousesView.vue`.
2. Teleport-based inline edit modal from the detail page.
3. Hard route or `window.location` navigation from detail to `/warehouses/{id}/edit`.
4. Custom floating modal contracts that expect `resourceId` and fetch/update their own record.

## Use this pattern instead

Warehouse follows the same CRM-style edit contract as Contacts, Companies, and Deals:

- Resource action: `Action::make()->floatResourceInEditMode()`.
- View action: `floatResourceInEditMode({ resourceName, resourceId })`.
- Global component name: `WarehouseFloatingModal`.
- Core modal props: `visible`, `floatingReady`, `resource`, `fields`, `mode`, `updateHandler`.
- Detail update sync: listen to `floating-resource-updated` and update/fetch the active record.

This file exists so RAG and the module builder can classify older debug patches as failed/superseded attempts, not as architecture guidance.
MD);

$historyDir = project_path('docs/ai/04-docops/history');
$historyMarker = '<!-- SUPERSEDED_BY_WAREHOUSE_FLOATING_MODAL_CONTRACT -->';
$patterns = [
    'warehouse-detail-inline-edit-modal-fix',
    'warehouse-detail-edit-click-and-modal-stability-fix',
    'warehouse-detail-edit-hard-route-fix',
    'warehouse-edit-contract-discovery',
    'warehouse-floating-modal-core-contract-fix',
    'warehouse-edit-first-party-floating-modal-fix',
];

if (is_dir($historyDir)) {
    foreach (glob($historyDir.'/*.md') ?: [] as $file) {
        $basename = basename($file);
        $shouldMark = false;

        foreach ($patterns as $pattern) {
            if (str_contains($basename, $pattern)) {
                $shouldMark = true;
                break;
            }
        }

        if (! $shouldMark) {
            continue;
        }

        $contents = file_get_contents($file);

        if (str_contains($contents, $historyMarker)) {
            continue;
        }

        $note = <<<MD
{$historyMarker}

> Superseded note: this history entry is retained for debugging/audit only. Do not use it as module-builder guidance. The canonical Warehouse edit contract is Core Floating Resource Modal + Core props + `floating-resource-updated` detail synchronization.

MD;
        file_put_contents($file, $note.$contents);
    }
}

$ragDir = 'docs/ai/05-rag/contracts';
write_file($ragDir.'/warehouse-edit-contract.json', json_encode([
    'module' => 'Warehouse',
    'feature' => 'floating_edit_modal',
    'status' => 'canonical',
    'date' => '2026-06-28',
    'use' => [
        'Action::make()->floatResourceInEditMode()',
        'useFloatingResourceModal().floatResourceInEditMode({ resourceName, resourceId })',
        'Global component name: WarehouseFloatingModal',
        'Core props: visible, floatingReady, resource, fields, mode, updateHandler',
        'Listen to floating-resource-updated in detail views for no-refresh synchronization',
    ],
    'do_not_use' => [
        'detail_inline_edit',
        'teleport_inline_edit',
        'hard_edit_route',
        'window.location edit navigation',
        'floating modal component requiring resourceId prop',
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

echo "Warehouse docs superseded attempts cleanup applied.\n";
