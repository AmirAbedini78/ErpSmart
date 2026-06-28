<?php

declare(strict_types=1);

$root = getcwd();
$viewPath = $root . '/modules/Warehouse/resources/js/views/WarehousesView.vue';
$contents = file_exists($viewPath) ? file_get_contents($viewPath) : '';

$checks = [
    'view_exists' => file_exists($viewPath),
    'uses_native_back_button' => str_contains($contents, '@click.stop.prevent="goBackToIndex"'),
    'uses_safe_back_text' => str_contains($contents, 'const backButtonText = computed(() =>'),
    'uses_native_edit_button' => str_contains($contents, '@click.stop.prevent="openInlineEditForm"'),
    'imports_next_tick' => str_contains($contents, 'nextTick'),
    'open_remounts_with_next_tick' => str_contains($contents, 'nextTick(() =>') && str_contains($contents, 'showInlineEditForm.value = true'),
    'teleports_edit_to_body' => str_contains($contents, '<Teleport to="body">') && str_contains($contents, '<WarehousesEdit'),
    'edit_uses_record_id' => str_contains($contents, ':record-id="warehouseId"'),
    'edit_redirect_disabled' => str_contains($contents, ':redirect-on-close="false"'),
    'back_no_longer_opens_edit' => ! preg_match('/back_to_warehouses[\s\S]{0,250}openInlineEditForm/', $contents),
];

foreach ($checks as $name => $passed) {
    printf("%-34s : %s\n", $name, $passed ? 'true' : 'false');
}

exit(in_array(false, $checks, true) ? 1 : 0);
