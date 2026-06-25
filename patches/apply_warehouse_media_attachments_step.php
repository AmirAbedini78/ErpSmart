<?php
/**
 * Warehouse Media / Attachments integration step.
 *
 * This script applies the discovered Core media contract to Warehouse without
 * touching the previously fixed Notes provider/model contract.
 *
 * Run from project root:
 *   docker compose exec app php patches/apply_warehouse_media_attachments_step.php
 */

function fail_step(string $message): never
{
    fwrite(STDERR, "ERROR: {$message}\n");
    exit(1);
}

function read_file_or_fail(string $path): string
{
    if (! is_file($path)) {
        fail_step("File not found: {$path}");
    }

    $contents = file_get_contents($path);

    if ($contents === false) {
        fail_step("Unable to read file: {$path}");
    }

    return $contents;
}

function backup_file(string $path): void
{
    $backup = $path.'.bak-media-'.date('YmdHis');

    if (! copy($path, $backup)) {
        fail_step("Unable to create backup: {$backup}");
    }

    echo "Backup created: {$backup}\n";
}

function write_if_changed(string $path, string $old, string $new): void
{
    if ($old === $new) {
        echo "No changes needed: {$path}\n";
        return;
    }

    backup_file($path);

    if (file_put_contents($path, $new) === false) {
        fail_step("Unable to write file: {$path}");
    }

    echo "Updated: {$path}\n";
}

function ensure_import(string $contents, string $import, string $afterImport): string
{
    if (str_contains($contents, $import)) {
        return $contents;
    }

    if (! str_contains($contents, $afterImport)) {
        fail_step("Import anchor not found: {$afterImport}");
    }

    return str_replace($afterImport, $afterImport."\n".$import, $contents);
}

$modelPath = 'modules/Warehouse/app/Models/Warehouse.php';
$resourcePath = 'modules/Warehouse/app/Resources/Warehouse.php';
$viewPath = 'modules/Warehouse/resources/js/views/WarehousesView.vue';

// 1) Warehouse model: add HasMedia trait.
$model = read_file_or_fail($modelPath);
$modelNew = $model;
$modelNew = ensure_import(
    $modelNew,
    'use Modules\\Core\\Common\\Media\\HasMedia;',
    'use Illuminate\\Database\\Eloquent\\Relations\\MorphToMany;'
);

if (! str_contains($modelNew, 'HasMedia,')) {
    if (str_contains($modelNew, "use HasTimeline,\n        Resourceable;")) {
        $modelNew = str_replace(
            "use HasTimeline,\n        Resourceable;",
            "use HasMedia,\n        HasTimeline,\n        Resourceable;",
            $modelNew
        );
    } elseif (str_contains($modelNew, 'use Resourceable;')) {
        $modelNew = str_replace('use Resourceable;', "use HasMedia,\n        Resourceable;", $modelNew);
    } else {
        fail_step('Could not locate Warehouse model trait block.');
    }
}

write_if_changed($modelPath, $model, $modelNew);

// 2) Warehouse resource: implement Mediable and register the standard Core media panel.
$resource = read_file_or_fail($resourcePath);
$resourceNew = $resource;
$resourceNew = ensure_import(
    $resourceNew,
    'use Modules\\Core\\Contracts\\Resources\\Mediable;',
    'use Modules\\Core\\Contracts\\Resources\\Importable;'
);
$resourceNew = ensure_import(
    $resourceNew,
    'use Modules\\Core\\Panel;',
    'use Modules\\Core\\Menu\\MenuItem;'
);

if (! preg_match('/class Warehouse extends Resource implements ([^\n]+)/', $resourceNew, $matches)) {
    fail_step('Could not locate Warehouse resource implements list.');
}

$implements = $matches[1];
if (! str_contains($implements, 'Mediable')) {
    $resourceNew = str_replace(
        'Importable, Tableable',
        'Importable, Mediable, Tableable',
        $resourceNew
    );
}

if (! str_contains($resourceNew, "function panels(ResourceRequest")) {
    $panelsMethod = <<<'PHP_METHOD'

    public function panels(ResourceRequest $request): array
    {
        return [
            Panel::make('media', 'resource-media-panel')
                ->heading(__('core::app.attachments')),
        ];
    }
PHP_METHOD;

    if (! str_contains($resourceNew, "    public function fields(ResourceRequest \$request): array\n")) {
        fail_step('Could not locate fields() method anchor in Warehouse resource.');
    }

    $resourceNew = str_replace(
        "    public function fields(ResourceRequest \$request): array\n",
        $panelsMethod."\n\n    public function fields(ResourceRequest \$request): array\n",
        $resourceNew
    );
}

write_if_changed($resourcePath, $resource, $resourceNew);

// 3) Warehouse detail view: add an Attachments tab powered by Core ResourceMediaPanel.
$view = read_file_or_fail($viewPath);
$viewNew = $view;

if (! str_contains($viewNew, "ResourceMediaPanel from '@/Core/components/Resource/ResourceMediaPanel.vue'")) {
    $anchor = "import RecordTabNotePanel from '@/Notes/components/RecordTabNotePanel.vue'";
    if (! str_contains($viewNew, $anchor)) {
        fail_step('Could not locate RecordTabNotePanel import anchor.');
    }

    $viewNew = str_replace(
        $anchor,
        $anchor."\nimport ResourceMediaPanel from '@/Core/components/Resource/ResourceMediaPanel.vue'",
        $viewNew
    );
}

if (! str_contains($viewNew, "core::app.attachments")) {
    $noteTab = <<<'VUE'
            <RecordTabNote
              :resource-name="resourceName"
              :resource-id="safeResource.id"
              :resource="safeResource"
            />
VUE;

    $mediaTab = <<<'VUE'
            <RecordTabNote
              :resource-name="resourceName"
              :resource-id="safeResource.id"
              :resource="safeResource"
            />

            <ITab>
              <Icon icon="PaperClip" />
              {{ $t('core::app.attachments') }}
            </ITab>
VUE;

    if (! str_contains($viewNew, $noteTab)) {
        fail_step('Could not locate Notes tab anchor in Warehouse view.');
    }

    $viewNew = str_replace($noteTab, $mediaTab, $viewNew);
}

if (! str_contains($viewNew, '<ResourceMediaPanel')) {
    $notePanel = <<<'VUE'
          <RecordTabNotePanel
            id="tabPanel-notes"
            scroll-element="#main"
            :resource-name="resourceName"
            :resource-id="safeResource.id"
            :resource="safeResource"
          />
VUE;

    $mediaPanel = <<<'VUE'
          <RecordTabNotePanel
            id="tabPanel-notes"
            scroll-element="#main"
            :resource-name="resourceName"
            :resource-id="safeResource.id"
            :resource="safeResource"
          />

          <ITabPanel>
            <ResourceMediaPanel
              :resource-name="resourceName"
              :resource-id="safeResource.id"
              :resource="safeResource"
            />
          </ITabPanel>
VUE;

    if (! str_contains($viewNew, $notePanel)) {
        fail_step('Could not locate Notes panel anchor in Warehouse view.');
    }

    $viewNew = str_replace($notePanel, $mediaPanel, $viewNew);
}

if (! str_contains($viewNew, 'media: Array.isArray(value.media) ? value.media : []')) {
    $anchor = "    notes_count: Number(value.notes_count || 0),\n";
    if (! str_contains($viewNew, $anchor)) {
        fail_step('Could not locate notes_count normalization anchor in Warehouse view.');
    }

    $viewNew = str_replace(
        $anchor,
        $anchor."\n    media: Array.isArray(value.media) ? value.media : [],\n    media_count: Number(value.media_count || (Array.isArray(value.media) ? value.media.length : 0)),\n",
        $viewNew
    );
}

write_if_changed($viewPath, $view, $viewNew);

echo "\nWarehouse media/attachments integration step applied.\n";
echo "Next commands:\n";
echo "  php artisan optimize:clear\n";
echo "  php artisan cache:clear\n";
echo "  npm run build\n";
