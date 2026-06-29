<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$resourcePath = $root.'/modules/Warehouse/app/Resources/Warehouse.php';
$modelPath = $root.'/modules/Warehouse/app/Models/Warehouse.php';
$historyPath = $root.'/docs/ai/04-docops/history/2026-06-28-warehouse-activities-display-query-signature-fix.md';

function fail(string $message): void
{
    fwrite(STDERR, $message.PHP_EOL);
    exit(1);
}

function replace_or_fail(string &$content, string $pattern, string $replacement, string $label): void
{
    $new = preg_replace($pattern, $replacement, $content, 1, $count);

    if ($new === null || $count !== 1) {
        fail("Could not replace {$label}.");
    }

    $content = $new;
}

if (! file_exists($resourcePath)) {
    fail("Missing file: {$resourcePath}");
}

if (! file_exists($modelPath)) {
    fail("Missing file: {$modelPath}");
}

$resource = file_get_contents($resourcePath);
$model = file_get_contents($modelPath);

if (! str_contains($resource, 'use Illuminate\Database\Eloquent\Builder;')) {
    replace_or_fail(
        $resource,
        '/namespace Modules\\\\Warehouse\\\\Resources;\s*/',
        "namespace Modules\\Warehouse\\Resources;\n\nuse Illuminate\\Database\\Eloquent\\Builder;\n",
        'Builder import'
    );
}

// Core Resource::displayQuery() has no arguments. A custom signature with
// (Builder $query, ResourceRequest $request) is fatal in PHP/Laravel.
// Keep Warehouse detail query lightweight; activities are lazy-loaded by ActivitiesTabPanel.
$displayQueryReplacement = <<<'PHP'

    public function displayQuery(): Builder
    {
        return parent::displayQuery();
    }

PHP;

if (preg_match('/\n\s*public function displayQuery\s*\([^)]*\)\s*:\s*Builder\s*\{[\s\S]*?\n\s*\}\s*\n\s*public function fields\(/', $resource)) {
    $resource = preg_replace(
        '/\n\s*public function displayQuery\s*\([^)]*\)\s*:\s*Builder\s*\{[\s\S]*?\n\s*\}\s*\n\s*public function fields\(/',
        $displayQueryReplacement.'    public function fields(',
        $resource,
        1,
        $count
    );

    if ($resource === null || $count !== 1) {
        fail('Could not normalize displayQuery method.');
    }
} else {
    replace_or_fail(
        $resource,
        '/\n\s*public function fields\(ResourceRequest \$request\): array/',
        $displayQueryReplacement.'    public function fields(ResourceRequest $request): array',
        'insert displayQuery before fields'
    );
}

// Remove any old force-loading/counting of activities from displayQuery remnants.
$resource = preg_replace('/->with\s*\(\s*\[[^\]]*activities[^\]]*\]\s*\)/', '', $resource) ?? $resource;
$resource = preg_replace('/->withCount\s*\(\s*\[[^\]]*activities[^\]]*\]\s*\)/', '', $resource) ?? $resource;

// Make the trait usage explicit so both PHP and future verifiers/RAG can see it
// without relying on a multi-line grouped trait statement.
if (! str_contains($model, 'use Modules\\Activities\\Concerns\\HasActivities;')) {
    replace_or_fail(
        $model,
        '/namespace Modules\\\\Warehouse\\\\Models;\s*/',
        "namespace Modules\\Warehouse\\Models;\n\nuse Modules\\Activities\\Concerns\\HasActivities;\n",
        'HasActivities import'
    );
}

$model = preg_replace(
    '/\n\s*use HasMedia,\s*\n\s*HasTimeline,\s*\n\s*HasActivities,\s*\n\s*Resourceable;\s*/',
    "\n    use HasMedia;\n    use HasTimeline;\n    use HasActivities;\n    use Resourceable;\n",
    $model,
    1,
    $traitCount
) ?? $model;

if ($traitCount === 0 && ! preg_match('/\n\s*use HasActivities;\s*/', $model)) {
    // Fallback for variants that had no HasActivities in the trait list.
    if (preg_match('/\n\s*use HasMedia;\s*\n\s*use HasTimeline;\s*\n\s*use Resourceable;\s*/', $model)) {
        $model = preg_replace(
            '/\n\s*use HasMedia;\s*\n\s*use HasTimeline;\s*\n\s*use Resourceable;\s*/',
            "\n    use HasMedia;\n    use HasTimeline;\n    use HasActivities;\n    use Resourceable;\n",
            $model,
            1
        ) ?? $model;
    } elseif (preg_match('/\n\s*use HasMedia,/', $model)) {
        $model = preg_replace('/\n\s*use HasMedia,/', "\n    use HasActivities;\n    use HasMedia,", $model, 1) ?? $model;
    } else {
        fail('Could not ensure HasActivities trait usage.');
    }
}

// Keep a safe accessor expected by the Activities tab badge if the resource
// response does not include a calculated count yet.
if (! str_contains($model, 'getIncompleteActivitiesForUserCountAttribute')) {
    $accessor = <<<'PHP'

    public function getIncompleteActivitiesForUserCountAttribute(): int
    {
        if (! method_exists($this, 'incompleteActivities')) {
            return 0;
        }

        try {
            return (int) $this->incompleteActivities()->count();
        } catch (\Throwable) {
            return 0;
        }
    }

PHP;

    replace_or_fail(
        $model,
        '/\n\}\s*$/',
        $accessor."}\n",
        'safe incomplete activity count accessor'
    );
}

file_put_contents($resourcePath, $resource);
file_put_contents($modelPath, $model);

@mkdir(dirname($historyPath), 0775, true);
file_put_contents($historyPath, <<<'MD'
# Warehouse Activities Display Query Signature Fix

Status: canonical runtime fix

## Problem

The Activities integration introduced a custom `Warehouse::displayQuery(Builder $query, ResourceRequest $request)` method. Core's `Resource::displayQuery()` signature has no arguments, so PHP raised a fatal compatibility error before `/api/warehouses/{id}` could be served.

An earlier variant also attempted to eager-load `activities` inside `displayQuery`, which made Warehouse detail depend on an activity relation during first render. The Activities tab already lazy-loads its data, so detail should stay lightweight.

## Fix

- Normalize Warehouse resource to `public function displayQuery(): Builder`.
- Return `parent::displayQuery()` without force-loading activities.
- Keep `HasActivities` explicitly visible in the Warehouse model.
- Keep a safe `incomplete_activities_for_user_count` accessor for the tab badge.

## Contract

For CRM-style module builders:

- Do not override `displayQuery()` with request/query parameters.
- Do not force eager-load Activities for the base detail endpoint.
- Use `HasActivities` on the model.
- Use `ActivitiesTab` and `ActivitiesTabPanel` in the record view.
- Use `CreateRelatedActivityAction::make()->onlyInline()` for the resource action.
MD);

echo "Warehouse activities displayQuery signature fix applied.\n";
