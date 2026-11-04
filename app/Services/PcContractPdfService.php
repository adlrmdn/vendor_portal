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

    public function __construct(private ?PcFdrLookupService $fdrService = null)
    {
        $this->fdrService = $fdrService ?? app(PcFdrLookupService::class);
    }

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

        $vendorCode = $contract->vendor?->code ?? '';

        foreach ($contract->lines as $line) {
            $item = $line->fabricItem;
            $fdrData = null;

            if ($line->item_number_snapshot) {
                try {
                    $fdrData = $this->fdrService->findForItemAndVendor($line->item_number_snapshot, $vendorCode);
                } catch (\Throwable $e) {
                    Log::warning("FDR lookup failed for item {$line->item_number_snapshot}: " . $e->getMessage());
                }
            }

            $parsed = $this->parseSpecsFromName($line->item_name_snapshot ?? $item?->item_name ?? '');

            $line->resolved_composition = $item?->composition ?: ($fdrData['composition'] ?? null) ?: ($parsed['composition'] ?? null);
            $line->resolved_construction = $item?->construction ?: ($fdrData['construction'] ?? null) ?: ($parsed['construction'] ?? null);
            $line->resolved_weave = $item?->weave ?: ($fdrData['weave'] ?? null) ?: ($parsed['weave'] ?? null);
            $line->resolved_full_width = $item?->full_width ?: ($fdrData['full_width'] ?? null) ?: ($parsed['full_width'] ?? null);
            $line->resolved_cut_width = $item?->cut_width ?: ($fdrData['cut_width'] ?? null) ?: ($parsed['cut_width'] ?? null);
            $line->resolved_weight = ($fdrData['fabric_weight'] ?? null) ?: ($parsed['weight'] ?? null) ?: ($item?->weight_gsm ? $item->weight_gsm . ' GSM' : null);
            $line->resolved_process = ($fdrData['fabric_process'] ?? null) ?: $item?->process ?: ($parsed['process'] ?? null);
            $line->resolved_finish = ($fdrData['fabric_finishing'] ?? null) ?: $item?->finish ?: ($parsed['finish'] ?? null);

            // Persist populated specs onto PcFabricItem if it exists and was blank
            if ($item && empty($item->composition) && ! empty($line->resolved_composition)) {
                try {
                    $item->composition = $line->resolved_composition;
                    $item->construction = $line->resolved_construction;
                    $item->weave = $line->resolved_weave;
                    $item->full_width = $line->resolved_full_width;
                    $item->cut_width = $line->resolved_cut_width;
                    $item->process = $line->resolved_process;
                    $item->finish = $line->resolved_finish;
                    if (empty($item->weight_gsm) && ! empty($line->resolved_weight)) {
                        $num = (float) filter_var($line->resolved_weight, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                        if ($num > 0) {
                            $item->weight_gsm = $num;
                        }
                    }
                    $item->save();
                } catch (\Throwable $e) {}
            }
        }

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
        ->setPaper([0, 0, 1650, 1275], 'portrait')
        ->setOption('isRemoteEnabled', true)
        ->setOption('isHtml5ParserEnabled', true)
        ->setOption('defaultFont', 'Helvetica');

        return $pdf->output();
    }

    /**
     * Fallback parser to extract specification details from fabric item name.
     */
    public function parseSpecsFromName(string $name): array
    {
        $specs = [
            'composition' => null,
            'construction' => null,
            'weave' => null,
            'cut_width' => null,
            'full_width' => null,
            'weight' => null,
            'process' => null,
            'finish' => null,
        ];

        if (empty($name)) {
            return $specs;
        }

        // Composition: e.g. 100% COTTON, 95% COTTON 5% SPANDEX, 100% POLYESTER
        if (preg_match('/\b(\d+%\s*[A-Z]+(?:\s+\d+%\s*[A-Z]+)*)\b/i', $name, $m)) {
            $specs['composition'] = trim($m[1]);
        }

        // Construction: e.g. 40X40/133X72, 7X6/68X48, 21X21/90X64
        if (preg_match('/\b(\d+[sS]?\s*[xX]\s*\d+[sS]?\s*\/\s*\d+\s*[xX]\s*\d+)\b/', $name, $m)) {
            $specs['construction'] = strtoupper(trim($m[1]));
        }

        // Cut width: e.g. 57/58" CW or 56/57" CW or 57/58 CW
        if (preg_match('/\b(\d{2}(?:\/\d{2})?(?:[\"”\']|\s*inch)?\s*CW)\b/i', $name, $m)) {
            $specs['cut_width'] = strtoupper(trim($m[1]));
        }

        // Full width: e.g. 58/59" FW or 57/58" FW
        if (preg_match('/\b(\d{2}(?:\/\d{2})?(?:[\"”\']|\s*inch)?\s*FW)\b/i', $name, $m)) {
            $specs['full_width'] = strtoupper(trim($m[1]));
        }

        // Weight: e.g. 120 GSM, 13 OZ, 180 G/M2
        if (preg_match('/\b(\d+(?:\.\d+)?\s*(?:GSM|OZ|G\/M2))\b/i', $name, $m)) {
            $specs['weight'] = strtoupper(trim($m[1]));
        }

        // Weave: e.g. TWILL 3/1, TWILL, PLAIN, DOBBY, OXFORD, POPLIN, RIB, SATIN, SATEEN
        if (preg_match('/\b(TWILL\s*\d\/\d|TWILL|PLAIN|DOBBY|OXFORD|POPLIN|RIB|SATIN|SATEEN|JERSEY|HERRINGBONE)\b/i', $name, $m)) {
            $specs['weave'] = strtoupper(trim($m[1]));
        }

        // Process: e.g. YARN DYED, PIECE DYED, PRINTED, SOLID, BLEACHED, GREIGE
        if (preg_match('/\b(YARN\s*DYED|PIECE\s*DYED|PRINT(?:ED)?|SOLID|BLEACHED|GREIGE)\b/i', $name, $m)) {
            $specs['process'] = strtoupper(trim($m[1]));
        }

        // Finish: e.g. SOFT, MERCERIZED, PEACH, SILKY, RESIN
        if (preg_match('/\b(SOFT(?:\s+[A-Z0-9\-]+)?|MERCERIZED|PEACH(?:ED)?|SILKY|RESIN)\b/i', $name, $m)) {
            $specs['finish'] = strtoupper(trim($m[1]));
        }

        return $specs;
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

