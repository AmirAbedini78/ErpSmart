<?php

$root = dirname(__DIR__);
$warehouseModelPath = $root.'/modules/Warehouse/app/Models/Warehouse.php';

function fail(string $message): never
{
    fwrite(STDERR, "ERROR: {$message}\n");
    exit(1);
}

function backup_file(string $path): void
{
    $backup = $path.'.bak-'.date('YmdHis');
    if (! copy($path, $backup)) {
        fail("Could not create backup for {$path}");
    }
    echo "Backup created: {$backup}\n";
}

if (! file_exists($warehouseModelPath)) {
    fail("Warehouse model not found at {$warehouseModelPath}");
}

$contents = file_get_contents($warehouseModelPath);
if ($contents === false) {
    fail("Could not read {$warehouseModelPath}");
}

$original = $contents;
backup_file($warehouseModelPath);

$contractImport = 'use Modules\\Core\\Contracts\\Resources\\Resourceable as ResourceableContract;';
$traitImport = 'use Modules\\Core\\Resource\\Resourceable;';

if (! str_contains($contents, $contractImport)) {
    if (str_contains($contents, $traitImport)) {
        $contents = str_replace($traitImport, $contractImport."\n".$traitImport, $contents);
    } else {
        $contents = str_replace("use Modules\\Core\\Models\\Model;\n", "use Modules\\Core\\Models\\Model;\n".$contractImport."\n", $contents);
    }
}

if (preg_match('/class\s+Warehouse\s+extends\s+Model\s+implements\s+ResourceableContract/', $contents) !== 1) {
    if (preg_match('/class\s+Warehouse\s+extends\s+Model\s+implements\s+([^\n{]+)/', $contents)) {
        $contents = preg_replace(
            '/class\s+Warehouse\s+extends\s+Model\s+implements\s+([^\n{]+)/',
            'class Warehouse extends Model implements ResourceableContract, $1',
            $contents,
            1
        );
    } else {
        $contents = preg_replace(
            '/class\s+Warehouse\s+extends\s+Model\s*\{/',
            "class Warehouse extends Model implements ResourceableContract\n{",
            $contents,
            1
        );
    }
}

if ($contents === null) {
    fail('Unexpected preg_replace failure.');
}

$contents = preg_replace('/implements\s+ResourceableContract,\s+ResourceableContract,/', 'implements ResourceableContract,', $contents);
$contents = preg_replace('/implements\s+ResourceableContract,\s+ResourceableContract/', 'implements ResourceableContract', $contents);

if (! str_contains($contents, $contractImport)) {
    fail('ResourceableContract import was not inserted.');
}

if (preg_match('/class\s+Warehouse\s+extends\s+Model\s+implements\s+([^\n{]*ResourceableContract[^\n{]*)/', $contents) !== 1) {
    fail('Warehouse class does not implement ResourceableContract after patch.');
}

if ($contents !== $original) {
    file_put_contents($warehouseModelPath, $contents);
    echo "Patched Warehouse model to implement Modules\\Core\\Contracts\\Resources\\Resourceable.\n";
} else {
    echo "Warehouse model was already patched.\n";
}

$syntax = shell_exec('php -l '.escapeshellarg($warehouseModelPath).' 2>&1');
echo trim((string) $syntax)."\n";

if (! str_contains((string) $syntax, 'No syntax errors detected')) {
    fail('Warehouse model has syntax errors after patch. Restore backup and inspect manually.');
}

echo "Warehouse media Resourceable contract fix applied.\n";
echo "Next commands:\n";
echo "  php artisan optimize:clear\n";
echo "  php artisan cache:clear\n";
