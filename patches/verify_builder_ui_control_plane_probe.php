<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);

$requiredDocs = [
    'docs/ai/03-architecture/module-builder-ui-control-plane.md',
    'docs/ai/03-architecture/module-builder-storage-strategy.md',
    'docs/ai/03-architecture/module-builder-performance-and-data-architecture.md',
    'docs/ai/03-architecture/module-builder-engine-boundaries.md',
    'docs/ai/03-architecture/current-custom-fields-storage-probe.md',
    'docs/ai/03-architecture/builder-ui-entrypoint-options.md',
    'docs/ai/04-docops/history/2026-07-01-builder-ui-control-plane-storage-and-engines.md',
];

$checks = [];

$addCheck = function (string $name, bool $result, string $type = 'REQUIRED') use (&$checks): void {
    $checks[] = [$type, $name, $result];
};

$read = function (string $path) use ($basePath): string {
    $fullPath = $basePath.'/'.$path;

    return is_file($fullPath) ? (string) file_get_contents($fullPath) : '';
};

foreach ($requiredDocs as $path) {
    $addCheck($path.' exists', is_file($basePath.'/'.$path));
}

$allDocs = '';
foreach ($requiredDocs as $path) {
    $allDocs .= "\n".$read($path);
}

$contains = function (array $needles) use ($allDocs): bool {
    foreach ($needles as $needle) {
        if (stripos($allDocs, $needle) === false) {
            return false;
        }
    }

    return true;
};

$addCheck('docs mention UI-first Builder', stripos($allDocs, 'UI-first Builder') !== false);
$addCheck('docs mention CLI as engineering harness only', $contains(['CLI', 'engineering harness only']));
$addCheck('docs mention Builder Studio', stripos($allDocs, 'Builder Studio') !== false);
$addCheck('docs mention embedded Super Admin/Settings customization', $contains(['embedded Super Admin/Settings customization']));
$addCheck('docs mention same backend Builder Control Plane', $contains(['same backend Builder Control Plane']));
$addCheck('docs mention current custom fields storage findings', $contains(['custom field metadata', 'physical columns', 'model_has_custom_field_options']));
$addCheck('docs mention JSON definitions are versioned, not sole source of truth', $contains(['JSON definitions are versioned, not sole source of truth']));
$addCheck('docs mention runtime business data should be relational/published, not only JSON', $contains(['runtime business data should be relational/published, not only JSON']));
$addCheck('docs mention Redis for cache/locks/queues/short-lived state', $contains(['Redis for cache, locks, queues', 'short-lived state']));
$addCheck('docs mention queue/job strategy for heavy work', $contains(['Queue/Job Strategy', 'validate definition', 'render preview']));
$addCheck('docs mention not running heavy generation in request lifecycle', $contains(['Do not run heavy generation in request lifecycle']));
$addCheck('docs mention AI/RAG derived indexes are not source of truth', $contains(['RAG indexes are derived', 'Vector DB is not source of truth']));
$addCheck('docs mention separate Builder RAG and Business Operations RAG', $contains(['Builder RAG', 'Business Operations RAG']));
$addCheck('docs mention future engine/microservice boundaries', $contains(['Later service boundary', 'extractable services later']));
$addCheck('docs mention modular monolith first, extractable services later', $contains(['modular monolith', 'extractable services later']));
$addCheck('docs mention rollback/publish manifest', $contains(['publish manifest', 'rollback manifest']));

$lifecyclePath = 'docs/ai/05-rag/contracts/builder-control-plane-lifecycle.json';
$lifecycleFullPath = $basePath.'/'.$lifecyclePath;
$lifecycleExists = is_file($lifecycleFullPath);
$addCheck($lifecyclePath.' exists', $lifecycleExists, 'INFO');

if ($lifecycleExists) {
    $json = json_decode((string) file_get_contents($lifecycleFullPath), true);
    $addCheck('optional lifecycle JSON is valid', json_last_error() === JSON_ERROR_NONE && is_array($json));
    $requiredStates = [
        'draft',
        'validating',
        'validated',
        'previewing',
        'previewed',
        'publish_pending',
        'publishing',
        'published',
        'publish_failed',
        'archived',
        'rolled_back',
    ];
    $states = is_array($json['states'] ?? null) ? $json['states'] : [];
    $addCheck('optional lifecycle JSON includes required states', count(array_diff($requiredStates, $states)) === 0);
    $addCheck('optional lifecycle JSON includes transitions', is_array($json['transitions'] ?? null) && count($json['transitions']) > 0);
}

$statusOutput = [];
$statusCode = 0;
exec(
    'git -c safe.directory='.escapeshellarg($basePath).' -C '.escapeshellarg($basePath).' status --short 2>&1',
    $statusOutput,
    $statusCode
);
$addCheck('git status command succeeded', $statusCode === 0);

$forbiddenPatterns = [
    'modules/Warehouse/',
    'modules/Core/',
    'database/migrations/',
    'vendor/',
    'node_modules/',
    'public/build/',
    'package.json',
    'package-lock.json',
    'composer.json',
    'composer.lock',
];

$forbiddenChanges = [];
foreach ($statusOutput as $line) {
    $path = trim(substr($line, 3));

    foreach ($forbiddenPatterns as $pattern) {
        if (str_starts_with($path, $pattern) || $path === $pattern) {
            $forbiddenChanges[] = $line;
        }
    }
}

$addCheck('no Core/Warehouse files changed', ! array_filter($forbiddenChanges, fn (string $line): bool => str_contains($line, 'modules/Core/') || str_contains($line, 'modules/Warehouse/')));
$addCheck('no migrations changed', ! array_filter($forbiddenChanges, fn (string $line): bool => str_contains($line, 'database/migrations/')));
$addCheck('no package/composer/vendor/build files changed', ! array_filter($forbiddenChanges, fn (string $line): bool => preg_match('#(vendor/|node_modules/|public/build/|package\.json|package-lock\.json|composer\.json|composer\.lock)#', $line) === 1));

foreach ($checks as [$type, $name, $result]) {
    echo sprintf("[%s] %s: %s\n", $type, $name, $result ? 'true' : 'false');
}

if ($forbiddenChanges !== []) {
    echo "Forbidden changes:\n";
    foreach ($forbiddenChanges as $line) {
        echo $line."\n";
    }
}

$failed = array_filter($checks, fn (array $check): bool => $check[0] === 'REQUIRED' && $check[2] === false);

echo $failed === [] ? "PASS\n" : "FAIL\n";

exit($failed === [] ? 0 : 1);
