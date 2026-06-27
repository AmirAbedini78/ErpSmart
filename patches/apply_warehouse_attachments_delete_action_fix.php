<?php
/**
 * Warehouse Attachments delete action fix.
 *
 * Upload and persistence already work. If the attachments list is visible but
 * the delete (X) action is missing, the custom Warehouse detail page is not
 * exposing the same authorization contract that Core detail pages expose to
 * ResourceMediaPanel / MediaItemsList.
 *
 * This patch keeps server-side security unchanged. It only fixes the UI contract:
 * - do not default record authorizations.update to false in the custom detail view;
 * - normalize each media item with a delete authorization flag so Core media list
 *   components can render their remove action when the backend allows it.
 *
 * Run from project root:
 *   docker compose exec app php patches/apply_warehouse_attachments_delete_action_fix.php
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

function write_if_changed(string $path, string $old, string $new): void
{
    if ($old === $new) {
        echo "No changes needed: {$path}\n";
        return;
    }

    $backup = $path.'.bak-attachments-delete-action-'.date('YmdHis');

    if (! copy($path, $backup)) {
        fail_step("Unable to create backup: {$backup}");
    }

    if (file_put_contents($path, $new) === false) {
        fail_step("Unable to write file: {$path}");
    }

    echo "Backup created: {$backup}\n";
    echo "Updated: {$path}\n";
}

$viewPath = 'modules/Warehouse/resources/js/views/WarehousesView.vue';
$view = read_file_or_fail($viewPath);
$viewNew = $view;

// 1) The earlier stability patch defaulted update=false to prevent inline edit
// crashes while the detail page was unstable. Now that fields are read-only via
// _edit_disabled, update=false blocks Core panel actions such as media removal.
$viewNew = str_replace(
    "      update: false,\n      delete: false,\n      ...(value.authorizations || {}),",
    "      update: true,\n      delete: false,\n      ...(value.authorizations || {}),",
    $viewNew
);

// Some projects may have spacing changed by formatters.
$viewNew = preg_replace(
    '/authorizations:\s*\{\s*view:\s*true,\s*update:\s*false,\s*delete:\s*false,\s*\.\.\.\(value\.authorizations\s*\|\|\s*\{\}\),\s*\}/s',
    "authorizations: {\n      view: true,\n      update: true,\n      delete: false,\n      ...(value.authorizations || {}),\n    }",
    $viewNew,
    1
) ?? $viewNew;

// 2) Normalize media items. Some Core media list templates render the delete X
// based on the media item authorization payload; custom resource pages can miss
// that payload when media is injected into a hand-built record object.
$oldMediaLine = '    media: Array.isArray(value.media) ? value.media : [],';
$newMediaBlock = <<<'JS'
    media: Array.isArray(value.media)
      ? value.media.map(media => ({
          ...media,
          authorizations: {
            delete: true,
            ...(media.authorizations || {}),
          },
        }))
      : [],
JS;

if (str_contains($viewNew, $oldMediaLine)) {
    $viewNew = str_replace($oldMediaLine, $newMediaBlock, $viewNew);
} elseif (! str_contains($viewNew, 'media.authorizations || {}') && preg_match('/media:\s*Array\.isArray\(value\.media\)\s*\?\s*value\.media\s*:\s*\[\],/s', $viewNew)) {
    $viewNew = preg_replace(
        '/media:\s*Array\.isArray\(value\.media\)\s*\?\s*value\.media\s*:\s*\[\],/s',
        trim($newMediaBlock),
        $viewNew,
        1
    ) ?? $viewNew;
}

if (! str_contains($viewNew, 'update: true') || ! str_contains($viewNew, 'media.authorizations || {}')) {
    fail_step('Could not confidently patch the Warehouse view. Please send modules/Warehouse/resources/js/views/WarehousesView.vue.');
}

write_if_changed($viewPath, $view, $viewNew);

$syntax = shell_exec('php -l '.escapeshellarg($viewPath).' 2>&1');
// php -l does not understand .vue files; only print when it is useful.
if (is_string($syntax) && ! str_contains($syntax, 'No syntax errors detected') && ! str_contains($syntax, 'unexpected token "<"')) {
    echo trim($syntax)."\n";
}

echo "\nWarehouse attachments delete action UI contract fix applied.\n";
echo "Next commands:\n";
echo "  php artisan optimize:clear\n";
echo "  php artisan cache:clear\n";
echo "  rm -f public/hot\n";
echo "  npm run build\n";
