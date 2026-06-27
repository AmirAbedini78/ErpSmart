<?php

$root = dirname(__DIR__);
$viewPath = $root.'/modules/Warehouse/resources/js/views/WarehousesView.vue';

if (! file_exists($viewPath)) {
    fwrite(STDERR, "View not found: {$viewPath}\n");
    exit(1);
}

$contents = file_get_contents($viewPath);
$checks = [
    'view_exists' => file_exists($viewPath),
    'imports_use_router' => preg_match('/useRouter/', $contents) === 1,
    'has_router_instance' => preg_match('/const\s+router\s*=\s*useRouter\s*\(/', $contents) === 1,
    'has_edit_path' => preg_match('/const\s+editPath\s*=\s*computed\s*\(/', $contents) === 1,
    'has_go_to_edit' => preg_match('/function\s+goToEdit\s*\(/', $contents) === 1,
    'edit_uses_canonical_path' => str_contains($contents, '/${resourceName}/${warehouseId.value}/edit') || str_contains($contents, '`/${resourceName}/${warehouseId.value}/edit`'),
    'button_calls_go_to_edit' => str_contains($contents, '@click.prevent="goToEdit"'),
];

foreach ($checks as $name => $result) {
    echo str_pad($name, 32).' : '.($result ? 'true' : 'false').PHP_EOL;
}

if (in_array(false, $checks, true)) {
    exit(1);
}
