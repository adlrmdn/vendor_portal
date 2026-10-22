<?php

namespace App\Services;

use App\Models\PcContract;
use App\Models\PcContractFile;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Renders + PERSISTS the confirmed contract's PDF. Unlike PackingSlip
 * (streamed on demand, never stored — its pdf_path column is dead), this
 * writes to disk and only creates the pc_contract_files row after the
 * write succeeds, so a row here never points at a missing file. Disk
 * defaults to 'local' (private) — no AWS bucket decision has been made
 * for PC specifically (see PC Phase 2 plan); swap to S3 later is a
 * one-column change on pc_contract_files.disk.
 *
 * Deliberately does NOT swallow a storage failure (unlike
 * RpaQueueService::offloadSignedDocToS3's try/catch-and-fall-back) — a
 * confirmed contract without a real stored PDF is a worse outcome than a
 * failed confirm, so this throws and lets the caller's transaction roll
 * back.
 */
class PcContractPdfService
{
    public function generate(PcContract $contract): PcContractFile
    {
        $contract->loadMissing([
            'vendor', 'currency', 'paymentTerm', 'deliveryTerm', 'transportMode',
            'vatTreatment', 'season', 'orderType',
            'lines.fabricItem', 'lines.plmRef', 'lines.hsCode', 'lines.deliveries.lot',
            'notes', 'tolerances',
        ]);

        $pdf = Pdf::loadView('admin.pc.contracts.pdf', ['contract' => $contract]);
        $bytes = $pdf->output();

        $path = "pc/contracts/{$contract->id}/{$contract->revision_no}/contract.pdf";

        // config/filesystems.php's 'local' disk sets throw=>false, so a
        // failed put() returns false rather than throwing — check the
        // return value explicitly rather than relying on a catch block.
        try {
            $written = Storage::disk('local')->put($path, $bytes);
        } catch (\Throwable $e) {
            $written = false;
            Log::error('PC contract PDF store exception: '.$e->getMessage(), ['contract_id' => $contract->id]);
        }

        if (! $written) {
            Log::error('PC contract PDF store failed.', ['contract_id' => $contract->id, 'path' => $path]);
            throw new \RuntimeException("Failed to store the PDF for contract {$contract->id} — confirm cannot succeed without it.");
        }

        $file = PcContractFile::create([
            'contract_id' => $contract->id,
            'file_type' => 'contract_pdf',
            'disk' => 'local',
            'path' => $path,
            'original_filename' => ($contract->contract_number ?: $contract->id).'.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($bytes),
            'revision_no' => $contract->revision_no,
            'generated_by' => $contract->approval_decision_by,
            'generated_at' => now(),
        ]);

        $contract->update(['pdf_generated_at' => now()]);

        return $file;
    }
}
