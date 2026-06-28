<?php

declare(strict_types=1);

$root = getcwd();

function check(string $label, bool $value): bool
{
    echo str_pad($label, 48).' : '.($value ? 'true' : 'false').PHP_EOL;

    return $value;
}

function project_path(string $relative): string
{
    global $root;

    return rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.ltrim($relative, DIRECTORY_SEPARATOR);
}

function contents(string $relative): string
{
    $path = project_path($relative);

    return is_file($path) ? file_get_contents($path) : '';
}

$canonical = contents('docs/ai/03-architecture/module-builder-edit-contract.md');
$superseded = contents('docs/ai/04-docops/superseded/warehouse-edit-attempts.md');
$rag = contents('docs/ai/05-rag/contracts/warehouse-edit-contract.json');
$historyFiles = glob(project_path('docs/ai/04-docops/history/*warehouse*edit*.md')) ?: [];
$markedHistory = array_filter($historyFiles, fn ($file) => str_contains(file_get_contents($file), 'SUPERSEDED_BY_WAREHOUSE_FLOATING_MODAL_CONTRACT'));

$checks = [];
$checks[] = check('canonical_doc_exists', $canonical !== '');
$checks[] = check('canonical_has_superseded_marker', str_contains($canonical, 'WAREHOUSE_EDIT_SUPERSEDED_ATTEMPTS_CANONICAL'));
$checks[] = check('canonical_says_do_not_use_inline', str_contains($canonical, 'detail_inline_edit') && str_contains($canonical, 'DO NOT USE'));
$checks[] = check('canonical_says_do_not_use_hard_route', str_contains($canonical, 'hard_edit_route'));
$checks[] = check('canonical_keeps_floating_modal_contract', str_contains($canonical, 'floatResourceInEditMode') && str_contains($canonical, 'updateHandler'));
$checks[] = check('superseded_doc_exists', $superseded !== '');
$checks[] = check('superseded_doc_classifies_attempts', str_contains($superseded, 'superseded') && str_contains($superseded, 'do not use'));
$checks[] = check('rag_contract_exists', $rag !== '');
$checks[] = check('rag_marks_canonical', str_contains($rag, '"status": "canonical"'));
$checks[] = check('rag_lists_do_not_use', str_contains($rag, '"do_not_use"') && str_contains($rag, 'teleport_inline_edit'));
$checks[] = check('history_attempts_marked_when_present', count($historyFiles) === 0 || count($markedHistory) > 0);

echo PHP_EOL.(in_array(false, $checks, true) ? 'FAILED' : 'OK').PHP_EOL;
