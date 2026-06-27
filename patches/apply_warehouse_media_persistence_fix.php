<?php
/**
 * Warehouse Media persistence fix.
 *
 * Upload already works, but media disappears after navigation because the
 * Warehouse record response does not carry the media relation when the record is
 * fetched again. For the current custom Warehouse detail page, we keep this
 * minimal and explicit by eager loading media on the Warehouse model.
 *
 * Run from project root:
 *   docker compose exec app php patches/apply_warehouse_media_persistence_fix.php
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

    $backup = $path.'.bak-media-persistence-'.date('YmdHis');

    if (! copy($path, $backup)) {
        fail_step("Unable to create backup: {$backup}");
    }

    if (file_put_contents($path, $new) === false) {
        fail_step("Unable to write file: {$path}");
    }

    echo "Backup created: {$backup}\n";
    echo "Updated: {$path}\n";
}

$modelPath = 'modules/Warehouse/app/Models/Warehouse.php';
$model = read_file_or_fail($modelPath);
$modelNew = $model;

if (! str_contains($modelNew, "protected $"."with = [")) {
    $anchor = <<<'PHP'
    protected $table = 'warehouses';
PHP;

    if (! str_contains($modelNew, $anchor)) {
        fail_step('Could not locate protected $table anchor in Warehouse model. Please inspect Warehouse.php manually.');
    }

    $addition = <<<'PHP'

    /**
     * The custom Warehouse detail page renders Core's ResourceMediaPanel directly.
     * Keep media eager loaded so attachments remain visible after navigating away
     * and returning to /warehouses/{id}.
     */
    protected $with = [
        'media',
    ];
PHP;

    $modelNew = str_replace($anchor, $anchor.$addition, $modelNew);
} elseif (! preg_match('/protected\s+\$with\s*=\s*\[[^\]]*[\'\"]media[\'\"]/s', $modelNew)) {
    $modelNew = preg_replace_callback(
        '/protected\s+\$with\s*=\s*\[(.*?)\];/s',
        function (array $matches): string {
            $inside = trim($matches[1]);
            if ($inside === '') {
                return "protected $"."with = [\n        'media',\n    ];";
            }

            return "protected $"."with = [".$matches[1]."\n        'media',\n    ];";
        },
        $modelNew,
        1
    ) ?? $modelNew;
}

write_if_changed($modelPath, $model, $modelNew);

$syntax = shell_exec('php -l '.escapeshellarg($modelPath).' 2>&1');
echo trim((string) $syntax)."\n";

if (! str_contains((string) $syntax, 'No syntax errors detected')) {
    fail_step('Warehouse model has syntax errors after patch. Restore backup and inspect manually.');
}

echo "Warehouse media persistence fix applied.\n";
echo "Next commands:\n";
echo "  php artisan optimize:clear\n";
echo "  php artisan cache:clear\n";
