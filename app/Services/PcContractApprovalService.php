<?php

namespace App\Services;

use App\Models\PcContract;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Handles internal approval of Preliminary Contracts.
 * Transitions pending_approval -> approved:
 * 1. Checks separation of duties (approver != preparer).
 * 2. Freezes master snapshot, standard clauses, and tolerance tables.
 * 3. Renders and securely stores the approved PDF (PC-A01 prerequisite).
 * 4. Supersedes the previous revision if this is a revision.
 */
class PcContractApprovalService
{
    public function __construct(
        private PcContractFreezeService $freezeService,
        private PcContractPdfService $pdfService,
    ) {}

    public function approve(PcContract $contract, User $approver): void
    {
        if ($contract->created_by === $approver->id) {
            throw new \RuntimeException('Separation of duties: the preparer cannot also approve this contract.');
        }

        DB::transaction(function () use ($contract, $approver) {
            $contract->approval_decision = 'approved';
            $contract->approval_decision_at = now();
            $contract->approval_decision_by = $approver->id;
            $contract->status = PcContract::STATUS_APPROVED;
            $contract->save();

            $this->freezeService->freeze($contract);
            $this->pdfService->generate($contract);

            if ($contract->previous_revision_id) {
                PcContract::where('id', $contract->previous_revision_id)->update(['status' => PcContract::STATUS_SUPERSEDED]);
            }
        });
    }
}
