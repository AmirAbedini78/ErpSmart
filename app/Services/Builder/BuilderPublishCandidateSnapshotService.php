<?php

namespace App\Services\Builder;

use App\Models\BuilderDefinition;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class BuilderPublishCandidateSnapshotService
{
    public function __construct(
        protected BuilderPublishReadinessAnalyzer $readinessAnalyzer,
        protected BuilderPublishDryRunGenerator $dryRunGenerator
    ) {
    }

    public function create(BuilderDefinition $definition): array
    {
        $readiness = $this->readinessAnalyzer->analyze($definition);
        $dryRun = $this->dryRunGenerator->generate($definition);
        $candidateId = now()->format('YmdHis').'-'.Str::lower(Str::random(8));
        $candidateRoot = 'storage/app/builder-publish-candidates/'.$definition->getKey().'/'.$candidateId;
        $candidateSnapshotPath = $candidateRoot.'/candidate-snapshot.json';

        $snapshot = [
            'generated_at' => now()->toIso8601String(),
            'candidate_id' => $candidateId,
            'definition_id' => $definition->getKey(),
            'definition_name' => $definition->name,
            'definition_checksum' => $definition->checksum,
            'candidate_root' => $candidateRoot,
            'candidate_snapshot_path' => $candidateSnapshotPath,
            'writes_performed' => 0,
            'runtime_writes_performed' => 0,
            'candidate_artifacts_written' => 1,
            'publish_executed' => false,
            'approval_requested' => false,
            'approval_granted' => false,
            'runtime_module_effect' => 'none',
            'readiness' => $readiness,
            'dry_run' => $dryRun,
            'candidate_status' => 'snapshot_created',
            'candidate_checklist' => $this->candidateChecklist($readiness, $dryRun),
            'forbidden_actions' => [
                'publish',
                'approve publish',
                'copy artifacts into runtime paths',
                'run migrations',
                'drop tables',
            ],
            'next_allowed_actions' => [
                'review candidate snapshot',
                'regenerate candidate snapshot',
                'archive definition',
            ],
            'safety' => [
                'snapshot_only' => true,
                'runtime_paths_touched' => false,
                'migrations_run' => false,
                'publish_executed' => false,
                'approval_persistence' => false,
            ],
        ];

        File::ensureDirectoryExists(base_path($candidateRoot));
        File::put(base_path($candidateSnapshotPath), $this->json($snapshot));

        return $snapshot;
    }

    protected function candidateChecklist(array $readiness, array $dryRun): array
    {
        return [
            $this->checklistItem('readiness_report_available', isset($readiness['status']) ? 'passed' : 'blocked', true),
            $this->checklistItem('dry_run_report_available', isset($dryRun['dry_run_root']) ? 'passed' : 'blocked', true),
            $this->checklistItem('dry_run_review_available', isset($dryRun['review']) ? 'passed' : 'blocked', true),
            $this->checklistItem('runtime_writes_zero', ($dryRun['runtime_writes_performed'] ?? null) === 0 ? 'passed' : 'blocked', true),
            $this->checklistItem('publish_not_executed', ($dryRun['publish_executed'] ?? null) === false ? 'passed' : 'blocked', true),
            $this->checklistItem('approval_not_requested', 'passed', true),
            $this->checklistItem('approval_not_granted', 'passed', true),
            $this->checklistItem('human_review_required_before_future_publish', 'not_checked', true),
        ];
    }

    protected function checklistItem(string $key, string $status, bool $required): array
    {
        return compact('key', 'status', 'required');
    }

    protected function json(array $value): string
    {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
