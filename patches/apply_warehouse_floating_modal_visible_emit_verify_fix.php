<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$componentPath = $root.'/modules/Warehouse/resources/js/components/WarehouseFloatingModal.vue';

function backup_file(string $path, string $suffix): void
{
    if (is_file($path)) {
        copy($path, $path.'.bak-'.$suffix.'-'.date('YmdHis'));
    }
}

if (! is_file($componentPath)) {
    fwrite(STDERR, "WarehouseFloatingModal.vue not found: {$componentPath}\n");
    exit(1);
}

$suffix = 'warehouse-floating-modal-visible-emit-verify-fix';
backup_file($componentPath, $suffix);

$component = file_get_contents($componentPath);

$component = str_replace(
    '@update:visible="$emit(\'update:visible\', $event)"',
    '@update:visible="emit(\'update:visible\', $event)"',
    $component
);

$component = str_replace(
    '@hidden="$emit(\'hidden\')"',
    '@hidden="emit(\'hidden\')"',
    $component
);

file_put_contents($componentPath, $component);

echo "Warehouse floating modal visible emit/verify fix applied.\n";
