<?php

/**
 * ERPSMART Warehouse Notes real relation fix.
 *
 * Why this exists:
 * - Note::resolveRelationUsing('warehouses', Closure) works for simple Eloquent reads.
 * - But laravel-pivot-events / LazyTouchesViaPivot can receive the dynamic closure name
 *   instead of the real relation name when syncing the Notes resource associations.
 * - That causes: Call to undefined method Modules\Notes\Models\Note::Modules\Warehouse\Providers\{closure}().
 *
 * This script adds a real Note::warehouses() method and removes the dynamic relation
 * registration from WarehouseServiceProvider.
 */

$root = dirname(__DIR__);

$notePath = $root.'/modules/Notes/app/Models/Note.php';
$providerPath = $root.'/modules/Warehouse/app/Providers/WarehouseServiceProvider.php';

function fail(string $message): never
{
    fwrite(STDERR, "ERROR: {$message}\n");
    exit(1);
}

function backup(string $path): void
{
    $backup = $path.'.bak-'.date('YmdHis');
    if (! copy($path, $backup)) {
        fail("Could not create backup for {$path}");
    }
    echo "Backup created: {$backup}\n";
}

if (! is_file($notePath)) {
    fail("Note model not found: {$notePath}");
}

if (! is_file($providerPath)) {
    fail("Warehouse service provider not found: {$providerPath}");
}

$note = file_get_contents($notePath);
$provider = file_get_contents($providerPath);

backup($notePath);
backup($providerPath);

// 1) Add real Note::warehouses() relation.
if (! str_contains($note, 'function warehouses(')) {
    if (! str_contains($note, 'use Illuminate\\Database\\Eloquent\\Relations\\MorphToMany;')) {
        $note = preg_replace(
            '/^namespace Modules\\\\Notes\\\\Models;\s*/m',
            "namespace Modules\\Notes\\Models;\n\nuse Illuminate\\Database\\Eloquent\\Relations\\MorphToMany;\n",
            $note,
            1
        );
    }

    if (! str_contains($note, 'use Modules\\Warehouse\\Models\\Warehouse;')) {
        $note = preg_replace(
            '/^(use .*?;\s*)/ms',
            "$1use Modules\\Warehouse\\Models\\Warehouse;\n",
            $note,
            1
        );
    }

    $method = <<<'PHP_METHOD'

    /**
     * Warehouses associated with this note.
     *
     * This must be a concrete model method instead of resolveRelationUsing(), because
     * the Core pivot touch system and laravel-pivot-events expect a stable relation
     * method name during sync(). Dynamic relation closures can be reported as the
     * relation name and cause BadMethodCallException.
     */
    public function warehouses(): MorphToMany
    {
        return $this->morphedByMany(Warehouse::class, 'noteable');
    }
PHP_METHOD;

    $pos = strrpos($note, '}');
    if ($pos === false) {
        fail('Could not find final closing brace in Note model.');
    }

    $note = substr($note, 0, $pos).$method."\n".substr($note, $pos);
    echo "Added real Note::warehouses() relation.\n";
} else {
    echo "Note::warehouses() already exists. Skipped.\n";
}

// 2) Remove dynamic relation registration from WarehouseServiceProvider.
$provider = str_replace("use Illuminate\\Database\\Eloquent\\Relations\\MorphToMany;\n", '', $provider);
$provider = str_replace("use Modules\\Notes\\Models\\Note;\n", '', $provider);
$provider = str_replace("        $"."this->registerNoteAssociations();\n", '', $provider);

$provider = preg_replace(
    '/\n\s*\/\*\*\s*\n\s*\* Register inverse relations required by Core resource association endpoints\..*?\n\s*protected function registerNoteAssociations\(\): void\s*\{.*?\n\s*\}\s*\n/s',
    "\n",
    $provider,
    1
);

if ($provider === null) {
    fail('Regex error while editing WarehouseServiceProvider.');
}

file_put_contents($notePath, $note);
file_put_contents($providerPath, $provider);

echo "Warehouse notes real relation fix applied.\n";
echo "Next commands:\n";
echo "  php artisan optimize:clear\n";
echo "  php artisan cache:clear\n";
echo "  php artisan permission:cache-reset\n";
