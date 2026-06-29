<?php

$root = dirname(__DIR__);

function path_join(string $root, string $path): string
{
    return rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.ltrim($path, DIRECTORY_SEPARATOR);
}

function contents(string $root, string $path): string
{
    $fullPath = path_join($root, $path);

    return is_file($fullPath) ? (file_get_contents($fullPath) ?: '') : '';
}

function has_all(string $haystack, array $needles): bool
{
    foreach ($needles as $needle) {
        if (! str_contains($haystack, $needle)) {
            return false;
        }
    }

    return true;
}

function has_regex(string $pattern, string $contents): bool
{
    return preg_match($pattern, $contents) === 1;
}

function print_check(string $type, string $name, bool $result): void
{
    printf('[%s] %-72s : %s%s', $type, $name, $result ? 'true' : 'false', PHP_EOL);
}

function git_changed_files(string $root): array
{
    $command = 'cd '.escapeshellarg($root).' && git status --porcelain --untracked-files=all 2>/dev/null';
    $output = shell_exec($command);

    if (! is_string($output) || trim($output) === '') {
        return [];
    }

    return array_values(array_filter(array_map(function (string $line): string {
        $path = trim(substr($line, 3));

        if (str_contains($path, ' -> ')) {
            $parts = explode(' -> ', $path);
            $path = end($parts);
        }

        return trim($path, "\" \t\n\r\0\x0B");
    }, explode(PHP_EOL, trim($output)))));
}

$paths = [
    'warehouse_resource' => 'modules/Warehouse/app/Resources/Warehouse.php',
    'warehouse_json_resource' => 'modules/Warehouse/app/Http/Resources/WarehouseResource.php',
    'warehouse_model' => 'modules/Warehouse/app/Models/Warehouse.php',
    'warehouse_view' => 'modules/Warehouse/resources/js/views/WarehousesView.vue',
    'standard_detail_page' => 'modules/Core/app/Pages/StandardDetailPage.php',
    'core_panel' => 'modules/Core/app/Pages/Panel.php',
    'core_tab' => 'modules/Core/app/Pages/Tab.php',
    'panels_vue' => 'modules/Core/resources/js/components/Panels.vue',
    'resource_details_panel' => 'modules/Core/resources/js/components/Resource/ResourceDetailsPanel.vue',
    'resource_media_panel' => 'modules/Core/resources/js/components/Resource/ResourceMediaPanel.vue',
    'contact_resource' => 'modules/Contacts/app/Resources/Contact.php',
    'company_resource' => 'modules/Contacts/app/Resources/Company.php',
    'deal_resource' => 'modules/Deals/app/Resources/Deal.php',
    'contact_view' => 'modules/Contacts/resources/js/views/ContactsView.vue',
    'company_view' => 'modules/Contacts/resources/js/views/CompaniesView.vue',
    'deal_view' => 'modules/Deals/resources/js/views/DealsView.vue',
    'activities_provider' => 'modules/Activities/app/Providers/ActivitiesServiceProvider.php',
    'notes_provider' => 'modules/Notes/app/Providers/NotesServiceProvider.php',
];

$files = [];
foreach ($paths as $key => $path) {
    $files[$key] = contents($root, $path);
}

$required = [
    '01_warehouse_resource_exists' => is_file(path_join($root, $paths['warehouse_resource'])),
    '02_warehouse_resource_extends_core_resource' => has_all($files['warehouse_resource'], [
        'use Modules\Core\Resource\Resource;',
        'class Warehouse extends Resource',
    ]),
    '03_warehouse_has_detail_view_true' => has_regex('/public\s+static\s+bool\s+\$hasDetailView\s*=\s*true\s*;/', $files['warehouse_resource']),
    '04_warehouse_json_resource_method_returns_http_resource' => has_all($files['warehouse_resource'], [
        'public function jsonResource(): string',
        'use Modules\Warehouse\Http\Resources\WarehouseResource;',
        'return WarehouseResource::class;',
    ]),
    '05_warehouse_json_resource_extends_core_json_resource' => has_all($files['warehouse_json_resource'], [
        'use Modules\Core\Resource\JsonResource;',
        'class WarehouseResource extends JsonResource',
    ]),
    '06_warehouse_json_resource_calls_with_common_data' => str_contains($files['warehouse_json_resource'], 'withCommonData('),
    '07_warehouse_model_uses_resourceable_media_timeline_activities' => has_all($files['warehouse_model'], [
        'use Modules\Core\Resource\Resourceable;',
        'use Modules\Core\Common\Media\HasMedia;',
        'use Modules\Core\Common\Timeline\HasTimeline;',
        'use Modules\Activities\Concerns\HasActivities;',
        'use HasMedia;',
        'use HasTimeline;',
        'use HasActivities;',
        'use Resourceable;',
    ]),
    '08_warehouse_resource_implements_expected_contracts' => has_all($files['warehouse_resource'], [
        'WithResourceRoutes',
        'Tableable',
        'Mediable',
        'Importable',
        'Exportable',
        'Cloneable',
        'AcceptsCustomFields',
        'AcceptsUniqueCustomFields',
    ]),
    '08b_warehouse_resource_pipes_comments_if_present' => ! str_contains($files['warehouse_resource'], 'PipesComments')
        || has_all($files['warehouse_resource'], [
            'use Modules\Comments\Contracts\PipesComments;',
            'PipesComments',
        ]),
    '09_warehouses_view_exists' => is_file(path_join($root, $paths['warehouse_view'])),
    '10_warehouses_view_renders_notes_tab_and_panel' => has_all($files['warehouse_view'], [
        'RecordTabNote',
        'RecordTabNotePanel',
        '<RecordTabNote',
        '<RecordTabNotePanel',
    ]),
    '11_warehouses_view_renders_activities_tab_and_panel' => has_all($files['warehouse_view'], [
        'ActivitiesTab',
        'ActivitiesTabPanel',
        '<ActivitiesTab',
        '<ActivitiesTabPanel',
    ]),
    '12_warehouses_view_renders_resource_media_panel' => has_all($files['warehouse_view'], [
        'ResourceMediaPanel',
        '<ResourceMediaPanel',
    ]),
    '13_warehouses_view_has_floating_edit_integration' => has_all($files['warehouse_view'], [
        'useFloatingResourceModal',
        'floatResourceInEditMode',
        'openEditFloatingModal',
    ]),
    '14_warehouses_view_uses_use_resource' => has_all($files['warehouse_view'], [
        'useResource(resourceName, warehouseId)',
        "const resourceName = Innoclapps.resourceName('warehouses')",
    ]),
];

$target = [
    '15_core_standard_detail_page_exists' => is_file(path_join($root, $paths['standard_detail_page'])),
    '16_core_panel_and_tab_classes_exist' => is_file(path_join($root, $paths['core_panel']))
        && is_file(path_join($root, $paths['core_tab'])),
    '17_panels_vue_exists' => is_file(path_join($root, $paths['panels_vue'])),
    '18_resource_details_panel_exists' => is_file(path_join($root, $paths['resource_details_panel'])),
    '19_resource_media_panel_exists' => is_file(path_join($root, $paths['resource_media_panel'])),
    '20_first_party_resources_register_detail_panels_tabs' => has_all($files['contact_resource'], [
        'protected function boot(): void',
        'getDetailPage()->tab(',
        'Tab::make(',
        'Panel::make(',
    ]) && has_all($files['company_resource'], [
        'protected function boot(): void',
        'getDetailPage()->tab(',
        'Tab::make(',
        'Panel::make(',
    ]) && has_all($files['deal_resource'], [
        'protected function boot(): void',
        'getDetailPage()->tab(',
        'Tab::make(',
        'Panel::make(',
    ]),
    '21_first_party_views_consume_resource_information_detail_page' => has_all($files['contact_view'], [
        'resourceInformation',
        'resourceInformation.value.detailPage',
        'page.panels',
        'page.tabs',
    ]) && has_all($files['company_view'], [
        'resourceInformation',
        'resourceInformation.value.detailPage',
        'page.panels',
        'page.tabs',
    ]) && has_all($files['deal_view'], [
        'resourceInformation',
        'resourceInformation.value.detailPage',
        'page.panels',
        'page.tabs',
    ]),
    '22_activities_and_notes_providers_inject_ordered_tabs' => has_all($files['activities_provider'], [
        "Tab::make('activities', 'activities-tab')",
        "->panel('activities-tab-panel')",
        '->order(15)',
        'getDetailPage()->tab($tab)',
    ]) && has_all($files['notes_provider'], [
        "Tab::make('notes', 'notes-tab')",
        "->panel('notes-tab-panel')",
        '->order(35)',
        'getDetailPage()->tab($tab)',
    ]),
];

$changedFiles = git_changed_files($root);
$unsafeCoreChanges = array_values(array_filter($changedFiles, fn (string $file): bool => str_starts_with($file, 'modules/Core/')));
$unsafeVendorChanges = array_values(array_filter($changedFiles, fn (string $file): bool => str_starts_with($file, 'vendor/')));
$unsafeMigrationChanges = array_values(array_filter($changedFiles, fn (string $file): bool => str_contains($file, '/database/migrations/')));
$unsafeManifestChanges = array_values(array_filter($changedFiles, fn (string $file): bool => in_array($file, [
    'composer.json',
    'composer.lock',
    'package.json',
    'package-lock.json',
], true)));

$safety = [
    '23_no_vendor_files_changed' => count($unsafeVendorChanges) === 0,
    '24_no_core_files_changed' => count($unsafeCoreChanges) === 0,
    '25_no_migrations_changed' => count($unsafeMigrationChanges) === 0,
    '26_no_package_or_composer_manifests_changed' => count($unsafeManifestChanges) === 0,
];

echo 'Warehouse StandardDetailPage Contract Verifier'.PHP_EOL;
echo 'Root: '.$root.PHP_EOL.PHP_EOL;

foreach ($required as $name => $result) {
    print_check('REQUIRED', $name, $result);
}

echo PHP_EOL;
foreach ($target as $name => $result) {
    print_check('TARGET', $name, $result);
}

echo PHP_EOL;
foreach ($safety as $name => $result) {
    print_check('SAFETY', $name, $result);
}

if ($changedFiles !== []) {
    echo PHP_EOL.'Changed files observed by git status:'.PHP_EOL;
    foreach ($changedFiles as $file) {
        echo '- '.$file.PHP_EOL;
    }
}

$requiredPassed = ! in_array(false, $required, true);
$safetyPassed = ! in_array(false, $safety, true);

echo PHP_EOL;
echo ($requiredPassed && $safetyPassed ? 'PASS' : 'FAIL').PHP_EOL;

exit($requiredPassed && $safetyPassed ? 0 : 1);
