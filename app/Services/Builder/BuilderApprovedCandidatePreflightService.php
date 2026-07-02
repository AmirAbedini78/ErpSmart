<?php

namespace App\Services\Builder;

use App\Models\BuilderDefinition;
use App\Models\BuilderPublishApprovalRequest;

class BuilderApprovedCandidatePreflightService
{
    public function check(BuilderDefinition $definition): array
    {
        $approval = $definition->publishApprovalRequests()
            ->where('status', BuilderPublishApprovalRequest::STATUS_APPROVED)
            ->latest('approved_at')
            ->first();

        $checks = [];
        $blockers = [];
        $warnings = [];

        $this->addCheck($checks, 'approved_request_exists', $approval !== null, true, $approval ? 'Approved request found.' : 'No approved candidate request exists.', $blockers);

        $snapshot = null;
        if ($approval) {
            $snapshotPath = base_path($approval->candidate_snapshot_path);
            $snapshot = is_file($snapshotPath) ? json_decode(file_get_contents($snapshotPath) ?: '', true) : null;
            $expiresAt = $approval->expires_at;
            $expired = $expiresAt !== null && $expiresAt->isPast();

            $this->addCheck($checks, 'approval_status_is_approved', $approval->status === BuilderPublishApprovalRequest::STATUS_APPROVED, true, 'Approval request status must be approved.', $blockers);
            $this->addCheck($checks, 'approval_not_revoked', $approval->status !== BuilderPublishApprovalRequest::STATUS_REVOKED, true, 'Approval is not revoked.', $blockers);
            $this->addCheck($checks, 'approval_not_rejected', $approval->status !== BuilderPublishApprovalRequest::STATUS_REJECTED, true, 'Approval is not rejected.', $blockers);
            $this->addCheck($checks, 'approval_not_invalidated', $approval->status !== BuilderPublishApprovalRequest::STATUS_INVALIDATED, true, 'Approval is not invalidated.', $blockers);
            $this->addCheck($checks, 'approval_not_expired', ! $expired, true, 'Approval is not expired.', $blockers);
            $this->addCheck($checks, 'definition_checksum_matches', $approval->definition_checksum === $definition->checksum, true, 'Approval checksum must match the current definition checksum.', $blockers);
            $this->addCheck($checks, 'candidate_id_exists', filled($approval->candidate_id), true, 'Candidate id must exist.', $blockers);
            $this->addCheck($checks, 'candidate_snapshot_path_exists', is_file($snapshotPath), true, 'Candidate snapshot file must exist.', $blockers);
            $this->addCheck($checks, 'candidate_snapshot_json_valid', is_array($snapshot), true, 'Candidate snapshot JSON must be valid.', $blockers);

            if (is_array($snapshot)) {
                $this->addCheck($checks, 'candidate_snapshot_publish_executed_false', ($snapshot['publish_executed'] ?? null) === false, true, 'Candidate snapshot must not have executed publish.', $blockers);
                $this->addCheck($checks, 'candidate_snapshot_runtime_writes_zero', ($snapshot['runtime_writes_performed'] ?? null) === 0, true, 'Candidate snapshot runtime writes must be zero.', $blockers);
                $this->addCheck($checks, 'dry_run_runtime_writes_zero', (($snapshot['dry_run']['runtime_writes_performed'] ?? 0) === 0), true, 'Dry-run runtime writes must be zero.', $blockers);
                $this->addCheck($checks, 'readiness_publish_executed_false', (($snapshot['readiness']['publish_executed'] ?? false) === false), true, 'Readiness report must not have executed publish.', $blockers);
            }
        }

        $this->addCheck($checks, 'approval_does_not_publish', true, true, 'Approval is review state only and does not publish.', $blockers);
        $this->addCheck($checks, 'future_publish_still_forbidden', true, true, 'Actual publish remains forbidden in this MVP.', $blockers);

        $status = $approval === null
            ? 'not_approved'
            : ($blockers !== [] ? 'blocked' : ($warnings !== [] ? 'warning' : 'eligible'));

        return [
            'safe' => $blockers === [],
            'eligible_for_future_publish' => $approval !== null && $blockers === [],
            'status' => $status,
            'writes_performed' => 0,
            'runtime_writes_performed' => 0,
            'publish_executed' => false,
            'runtime_module_effect' => 'none',
            'definition_id' => $definition->getKey(),
            'definition_checksum' => $definition->checksum,
            'approval_request' => $approval ? [
                'id' => $approval->getKey(),
                'status' => $approval->status,
                'candidate_id' => $approval->candidate_id,
                'candidate_snapshot_path' => $approval->candidate_snapshot_path,
                'definition_checksum' => $approval->definition_checksum,
                'approved_at' => $approval->approved_at?->toIso8601String(),
            ] : null,
            'checks' => $checks,
            'blockers' => array_values(array_unique($blockers)),
            'warnings' => $warnings,
            'forbidden_actions' => [
                'publish',
                'copy artifacts into runtime paths',
                'run migrations',
                'drop tables',
            ],
            'next_allowed_actions' => [
                'review preflight report',
                'regenerate candidate snapshot',
                'request new approval if stale',
            ],
        ];
    }

    protected function addCheck(array &$checks, string $key, bool $passed, bool $required, string $message, array &$blockers): void
    {
        $status = $passed ? 'passed' : ($required ? 'blocked' : 'warning');
        $checks[] = compact('key', 'status', 'required', 'message');

        if (! $passed && $required) {
            $blockers[] = $message;
        }
    }
}
