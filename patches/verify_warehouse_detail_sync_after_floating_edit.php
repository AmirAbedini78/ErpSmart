<?php

declare(strict_types=1);

$root = dirname(__DIR__);

function path_join(string ...$parts): string
{
    return preg_replace('#/+#', '/', implode('/', $parts));
}

function read_if_exists(string $path): string
{
    return is_file($path) ? (string) file_get_contents($path) : '';
}

function check(string $label, bool $result): void
{
    echo str_pad($label, 45).': '.($result ? 'true' : 'false').PHP_EOL;
}

$view = read_if_exists(path_join($root, 'modules/Warehouse/resources/js/views/WarehousesView.vue'));
$resource = read_if_exists(path_join($root, 'modules/Warehouse/app/Resources/Warehouse.php'));
$app = read_if_exists(path_join($root, 'modules/Warehouse/resources/js/app.js'));
$floating = read_if_exists(path_join($root, 'modules/Warehouse/resources/js/components/WarehouseFloatingModal.vue'));
$architecture = read_if_exists(path_join($root, 'docs/ai/03-architecture/module-builder-edit-contract.md'));
$rag = read_if_exists(path_join($root, 'docs/ai/05-rag/module-manifest/warehouse-edit-contract.json'));

check('view_exists', $view !== '');
check('imports_on_global', str_contains($view, "@/Core/composables/useGlobalEventListener") && str_contains($view, 'onGlobal'));
check('listens_to_floating_resource_updated', str_contains($view, "onGlobal('floating-resource-updated'"));
check('matches_same_resource_name', str_contains($view, 'payload.resourceName === resourceName'));
check('matches_same_resource_id', str_contains($view, 'String(payload.resourceId) === String(warehouseId.value)'));
check('syncs_payload_resource', str_contains($view, 'synchronizeResource(normalizeResource(payload.resource), true)'));
check('fetches_after_floating_update', str_contains($view, 'await fetchResource()'));
check('normalizes_after_floating_update', str_contains($view, 'normalizeCurrentResource()'));
check('sets_resource_after_floating_update', str_contains($view, 'setResource(resource)'));
check('view_class_typo_fixed', ! str_contains($view, 'sm:flex-rowsm:items-start'));
check('floating_modal_registered', str_contains($app, "app.component('WarehouseFloatingModal'"));
check('floating_modal_core_contract', str_contains($floating, 'updateHandler') && str_contains($floating, 'floatingReady'));
check('resource_has_float_edit_action', str_contains($resource, 'floatResourceInEditMode'));
check('docs_canonical_contract_updated', str_contains($architecture, 'Status: canonical') && str_contains($architecture, 'floating-resource-updated'));
check('docs_superseded_attempts_marked', str_contains($architecture, 'Superseded attempts') && str_contains($architecture, 'do not use'));
check('rag_contract_file_exists', $rag !== '' && str_contains($rag, 'floating_edit_modal'));
