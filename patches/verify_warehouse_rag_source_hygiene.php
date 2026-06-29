<?php

$root = dirname(__DIR__);
$manifestPath = $root.'/docs/ai/05-rag/exclusions/warehouse-canonical-template-exclusions.json';

function check(string $name, bool $result): bool
{
    printf('%-72s : %s%s', $name, $result ? 'true' : 'false', PHP_EOL);

    return $result;
}

function normalize_path(string $path): string
{
    return trim(str_replace('\\', '/', $path), '/');
}

function pattern_to_regex(string $pattern): string
{
    $pattern = normalize_path($pattern);
    $quoted = preg_quote($pattern, '#');
    $quoted = str_replace('\*\*', '.*', $quoted);
    $quoted = str_replace('\*', '[^/]*', $quoted);

    return '#^'.$quoted.'$#';
}

function matches_pattern(string $path, string $pattern): bool
{
    $path = normalize_path($path);
    $pattern = normalize_path($pattern);

    if (fnmatch($pattern, $path, FNM_PATHNAME)) {
        return true;
    }

    return preg_match(pattern_to_regex($pattern), $path) === 1;
}

function matches_any(string $path, array $patterns): bool
{
    foreach ($patterns as $pattern) {
        if (matches_pattern($path, $pattern)) {
            return true;
        }
    }

    return false;
}

function path_exists_for_evidence(string $root, string $path): bool
{
    return file_exists($root.'/'.normalize_path($path));
}

if (! is_file($manifestPath)) {
    echo 'Manifest missing: '.$manifestPath.PHP_EOL;
    exit(1);
}

$manifest = json_decode(file_get_contents($manifestPath) ?: '', true);

if (! is_array($manifest)) {
    echo 'Manifest JSON is invalid.'.PHP_EOL;
    exit(1);
}

$patterns = $manifest['exclusion_patterns'] ?? [];
$canonicalEvidence = $manifest['canonical_evidence'] ?? [];
$curatedReports = $manifest['curated_storage_reports'] ?? [];
$allCuratedInputs = array_merge($canonicalEvidence, $curatedReports);

echo 'Warehouse RAG Source Hygiene Verifier'.PHP_EOL;
echo 'Manifest: docs/ai/05-rag/exclusions/warehouse-canonical-template-exclusions.json'.PHP_EOL.PHP_EOL;

echo 'Exclusion patterns:'.PHP_EOL;
foreach ($patterns as $pattern) {
    echo '- '.$pattern.PHP_EOL;
}
echo PHP_EOL;

$requiredCanonical = [
    'modules/Warehouse/app/Resources/Warehouse.php',
    'modules/Warehouse/app/Models/Warehouse.php',
    'modules/Warehouse/app/Http/Resources/WarehouseResource.php',
    'modules/Warehouse/resources/js/views/WarehousesView.vue',
    'patches/verify_warehouse_standard_detail_page_contract.php',
    'docs/ai/02-domains/warehouse.md',
    'docs/ai/03-architecture/resource-detail-capability-matrix.md',
    'docs/ai/05-rag/module-manifest/warehouse.json',
    'docs/ai/04-docops/history/2026-06-29-warehouse-standard-detail-page-verifier.md',
    'docs/ai/04-docops/history/2026-06-29-warehouse-standard-detail-page-backend-metadata.md',
    'docs/ai/04-docops/history/2026-06-29-warehouse-standard-detail-page-frontend-conversion.md',
];

$requiredPatterns = [
    '*.bak-*',
    'modules/Warehouse/**/*.bak-*',
    'public/build/**',
    'node_modules/**',
    'vendor/**',
    'storage/framework/**',
    'storage/logs/**',
    'storage/app/**',
    'docs/ai/04-docops/superseded/**',
    'composer.lock',
    'package-lock.json',
];

$failed = false;

$failed = ! check('manifest_has_exclusion_patterns', count($patterns) > 0) || $failed;
$failed = ! check('manifest_has_canonical_evidence', count($canonicalEvidence) > 0) || $failed;
$failed = ! check('manifest_has_curated_storage_reports', count($curatedReports) > 0) || $failed;

foreach ($requiredPatterns as $pattern) {
    $failed = ! check('required_exclusion_pattern_present: '.$pattern, in_array($pattern, $patterns, true)) || $failed;
}

foreach ($requiredCanonical as $path) {
    $inManifest = in_array($path, $canonicalEvidence, true);
    $exists = path_exists_for_evidence($root, $path);
    $allowed = ! matches_any($path, $patterns);

    $failed = ! check('canonical_listed: '.$path, $inManifest) || $failed;
    $failed = ! check('canonical_exists: '.$path, $exists) || $failed;
    $failed = ! check('canonical_not_excluded: '.$path, $allowed) || $failed;
}

$excludedCanonical = [];
$backupCurated = [];
$missingCurated = [];

foreach ($allCuratedInputs as $path) {
    if (str_contains($path, '.bak-')) {
        $backupCurated[] = $path;
    }

    if (! path_exists_for_evidence($root, $path)) {
        $missingCurated[] = $path;
    }
}

foreach ($canonicalEvidence as $path) {
    if (matches_any($path, $patterns)) {
        $excludedCanonical[] = $path;
    }
}

$failed = ! check('no_excluded_paths_in_canonical_evidence', $excludedCanonical === []) || $failed;
$failed = ! check('no_backup_paths_in_curated_inputs', $backupCurated === []) || $failed;
$failed = ! check('all_curated_inputs_exist', $missingCurated === []) || $failed;

$warehouseBackupFiles = [];
$warehouseDirectory = $root.'/modules/Warehouse';

if (is_dir($warehouseDirectory)) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($warehouseDirectory, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $fileInfo) {
        if ($fileInfo->isFile() && str_contains($fileInfo->getFilename(), '.bak-')) {
            $warehouseBackupFiles[] = $fileInfo->getPathname();
        }
    }
}

echo PHP_EOL.'Warehouse backup files found and excluded from canonical evidence: '.count($warehouseBackupFiles).PHP_EOL;
foreach (array_slice($warehouseBackupFiles, 0, 20) as $path) {
    echo '- '.normalize_path(substr($path, strlen($root) + 1)).PHP_EOL;
}

if (count($warehouseBackupFiles) > 20) {
    echo '- ... '.(count($warehouseBackupFiles) - 20).' more'.PHP_EOL;
}

if ($excludedCanonical !== []) {
    echo PHP_EOL.'Excluded paths incorrectly listed as canonical evidence:'.PHP_EOL;
    foreach ($excludedCanonical as $path) {
        echo '- '.$path.PHP_EOL;
    }
}

if ($backupCurated !== []) {
    echo PHP_EOL.'Backup paths incorrectly listed as curated evidence:'.PHP_EOL;
    foreach ($backupCurated as $path) {
        echo '- '.$path.PHP_EOL;
    }
}

if ($missingCurated !== []) {
    echo PHP_EOL.'Curated evidence paths missing on disk:'.PHP_EOL;
    foreach ($missingCurated as $path) {
        echo '- '.$path.PHP_EOL;
    }
}

echo PHP_EOL.($failed ? 'FAIL' : 'PASS').PHP_EOL;

exit($failed ? 1 : 0);
