<?php

namespace App\Services;

use App\Models\PcContract;
use App\Models\PcContractFile;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Renders + PERSISTS the confirmed/approved contract's PDF matching the v4 specification.
 * Paper geometry: 1650 x 1275 pt landscape with exact table columns,
 * standard notes, shipment tolerance tables, and electronic signatures.
 */
class PcContractPdfService
{
    private const CURRENCY_NAMES = [
        'USD' => 'UNITED STATES DOLLAR',
        'IDR' => 'INDONESIAN RUPIAH',
        'RMB' => 'CHINESE YUAN',
    ];

    private const CURRENCY_SYMBOLS = [
        'USD' => '$',
        'IDR' => 'Rp',
        'RMB' => '¥',
    ];

    /**
     * Compute verification code over frozen contract content.
     */
    public function computeVerificationCode(PcContract $contract): string
    {
        $parts = [
            (string) ($contract->contract_number ?? ''),
            (string) ($contract->revision_no ?? 0),
            (string) ($contract->vendor?->code ?? $contract->vendor?->name ?? ''),
            (string) ($contract->contract_date ?? ''),
            (string) ($contract->currency?->code ?? 'USD'),
        ];

        foreach ($contract->lines as $line) {
            $parts[] = (string) ($line->item_number_snapshot ?? '');
            $parts[] = (string) ($line->plm_id_snapshot ?? '');
            $parts[] = (string) ($line->qty ?? 0);
            $parts[] = (string) ($line->unit_price ?? 0);
        }

        $parts[] = (string) ($contract->approval_decision_at ?? '');

        $hash = strtoupper(substr(hash('sha256', implode('|', $parts)), 0, 12));

        return substr($hash, 0, 4) . '-' . substr($hash, 4, 4) . '-' . substr($hash, 8, 4);
    }

    /**
     * Render the contract PDF as raw binary string.
     */
    public function render(PcContract $contract): string
    {
        $contract->loadMissing([
            'vendor', 'currency', 'paymentTerm', 'deliveryTerm', 'transportMode',
            'vatTreatment', 'season', 'orderType', 'creator', 'approver', 'deliveries.lot',
            'lines.fabricItem', 'lines.plmRef', 'lines.hsCode', 'lines.deliveries.lot',
            'notes', 'tolerances',
        ]);

        $logoBase64 = null;
        $logoPath = public_path('images/pc_mpg_logo.png');
        if (! file_exists($logoPath)) {
            $logoPath = public_path('images/mpg_logo.png');
        }
        if (file_exists($logoPath)) {
            $logoBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
        }

        $currencyCode = $contract->currency?->code ?? 'USD';
        $currencyName = self::CURRENCY_NAMES[$currencyCode] ?? $currencyCode;
        $currencySymbol = self::CURRENCY_SYMBOLS[$currencyCode] ?? $currencyCode;

        $verificationCode = $this->computeVerificationCode($contract);

        $pdf = Pdf::loadView('admin.pc.contracts.pdf', [
            'contract' => $contract,
            'logoBase64' => $logoBase64,
            'currencyName' => $currencyName,
            'currencySymbol' => $currencySymbol,
            'verificationCode' => $verificationCode,
        ])
        ->setPaper([0, 0, 1650, 1275], 'landscape')
        ->setOption('isRemoteEnabled', true)
        ->setOption('isHtml5ParserEnabled', true)
        ->setOption('defaultFont', 'Helvetica');

        return $pdf->output();
    }

    /**
     * Generate and persist the PDF file.
     */
    public function generate(PcContract $contract): PcContractFile
    {
        $bytes = $this->render($contract);

        $path = "pc/contracts/{$contract->id}/{$contract->revision_no}/contract.pdf";

        try {
            $written = Storage::disk('local')->put($path, $bytes);
        } catch (\Throwable $e) {
            $written = false;
            Log::error('PC contract PDF store exception: ' . $e->getMessage(), ['contract_id' => $contract->id]);
        }

        if (! $written) {
            Log::error('PC contract PDF store failed.', ['contract_id' => $contract->id, 'path' => $path]);
            throw new \RuntimeException("Failed to store the PDF for contract {$contract->id} — cannot proceed without stored document.");
        }

        $file = PcContractFile::updateOrCreate(
            [
                'contract_id' => $contract->id,
                'revision_no' => $contract->revision_no,
                'file_type' => 'contract_pdf',
            ],
            [
                'disk' => 'local',
                'path' => $path,
                'original_filename' => ($contract->contract_number ?: $contract->id) . ($contract->revision_no > 0 ? " (REV {$contract->revision_no})" : '') . '.pdf',
                'mime_type' => 'application/pdf',
                'size_bytes' => strlen($bytes),
                'generated_by' => $contract->approval_decision_by ?? $contract->created_by,
                'generated_at' => now(),
            ]
        );

        $contract->update(['pdf_generated_at' => now()]);

        return $file;
    }
}

