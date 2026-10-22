<?php

namespace App\Services;

use App\Models\PcContract;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The one transaction that moves a contract from pending_final_approval
 * to confirmed: number issuance + freeze + PDF, in that order, plus
 * superseding the previous revision if this is one. Separation-of-duties
 * is re-checked here as defense-in-depth (the controller already checked
 * it before consuming the TOTP window) — never call this without that
 * earlier check too.
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
            $contract->confirmed_at = now();
            $contract->confirmed_by = $approver->id;
            $contract->status = 'confirmed';
            $contract->save();

            $this->freezeService->freeze($contract);
            $this->pdfService->generate($contract);

            if ($contract->previous_revision_id) {
                PcContract::where('id', $contract->previous_revision_id)->update(['status' => 'superseded']);
            }
        });
    }
}
