<?php

namespace App\Services;

use App\Models\PcContract;
use App\Models\PcContractFile;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class PcAnalysisExportService
{
    /**
     * Generate the 3 daily analysis files:
     * - pc_contract_lines.csv
     * - pc_contracts.csv
     * - manifest.json
     *
     * Stored in analysis/latest/ and analysis/archive/YYYY-MM-DD/
     */
    public function generateExport(): array
    {
        $now = now();
        $dateStr = $now->toDateString();
        $timestampIso = $now->toISOString();

        // Load all contracts with relations
        $contracts = PcContract::with([
            'lines.fabricItem',
            'lines.plmRef',
            'lines.hsCode',
            'deliveries.lot',
            'vendor.paymentTerm',
            'season',
            'currency',
            'orderType',
            'transportMode',
            'deliveryTerm',
            'vatTreatment',
            'creator',
            'approver',
            'previousRevision',
            'nextRevision',
            'files',
        ])->orderBy('contract_number')->orderBy('revision_no')->get();

        // Cache users for ID lookups (submitted_by, vendor_decision_by, confirmed_by, cancelled_by)
        $userIds = $contracts->pluck('cancelled_by')
            ->merge($contracts->pluck('submitted_by'))
            ->merge($contracts->pluck('vendor_decision_by'))
            ->merge($contracts->pluck('confirmed_by'))
            ->filter()->unique()->all();
        $usersMap = !empty($userIds) ? User::whereIn('id', $userIds)->pluck('name', 'id')->all() : [];

        // 1. Generate pc_contract_lines.csv
        $linesHandle = fopen('php://temp', 'r+');
        $linesHeader = [
            'contract_number', 'revision_no', 'line_no', 'status', 'is_current', 'is_cancelled',
            'superseded_by_revision', 'revision_reason', 'contract_date', 'season_code',
            'order_type_code', 'payment_term_label', 'delivery_term_label', 'transport_mode_label',
            'vat_treatment', 'vat_rate', 'delivery_dates', 'contract_remarks', 'vendor_code',
            'supplier_name', 'supplier_group_code', 'supplier_country', 'contact_person',
            'currency_code', 'style_brand', 'plm_id', 'lot', 'item_number', 'item_name',
            'composition', 'construction', 'weave', 'full_width', 'cut_width', 'weight_gsm',
            'process', 'finish', 'hs_code', 'quantity', 'uom_code', 'unit_price',
            'unit_price_ex_vat', 'line_total', 'line_total_ex_vat', 'price_override_reason',
            'line_remarks', 'contract_total_quantity', 'contract_total_value', 'created_at',
            'prepared_by', 'prepared_at', 'approved_by', 'approved_at', 'sent_at',
            'supplier_response', 'supplier_responded_by', 'supplier_responded_at',
            'supplier_query_reason', 'rejected_reason', 'cancelled_by', 'cancelled_at',
            'cancel_reason', 'approved_pdf_key', 'accepted_pdf_key', 'updated_at',
            'export_generated_at',
        ];
        fputcsv($linesHandle, $linesHeader);

        // 2. Generate pc_contracts.csv
        $contractsHandle = fopen('php://temp', 'r+');
        $contractsHeader = [
            'contract_number', 'revision_no', 'status', 'is_current', 'contract_date',
            'season_code', 'order_type_code', 'vendor_code', 'supplier_name',
            'supplier_group_code', 'currency_code', 'payment_term_label', 'delivery_term_label',
            'transport_mode_label', 'vat_treatment', 'vat_rate', 'line_count', 'total_quantity',
            'total_value', 'total_value_ex_vat', 'created_at', 'prepared_by', 'prepared_at',
            'approved_by', 'approved_at', 'sent_at', 'supplier_response', 'supplier_responded_at',
            'revision_reason', 'rejected_reason', 'cancelled_by', 'cancelled_at',
            'cancel_reason', 'updated_at',
        ];
        fputcsv($contractsHandle, $contractsHeader);

        $totalLinesCount = 0;
        $statusCounts = [];

        foreach ($contracts as $c) {
            $statusCounts[$c->status] = ($statusCounts[$c->status] ?? 0) + 1;

            $vendor = $c->vendor;
            $vendorCode = $vendor?->vendor_code ?? '';
            $supplierName = $vendor?->name ?? '';
            $currencyCode = $c->currency?->code ?? $vendor?->currency_code ?? 'USD';
            $seasonCode = $c->season?->code ?? '';
            $orderTypeCode = $c->orderType?->code ?? '';
            $paymentTerm = $c->paymentTerm?->label ?? $vendor?->paymentTerm?->label ?? '';
            $deliveryTerm = $c->deliveryTerm?->label ?? '';
            $transportMode = $c->transportMode?->label ?? '';
            $vatTreatment = $c->vatTreatment?->code ?? 'EXCLUDE';
            $vatRate = (float) ($c->vat_rate ?? 11.00);

            $deliveryDates = $c->deliveries->map(function ($d) {
                return ($d->lot?->label ?? 'LOT').' '.($d->eta_date ? $d->eta_date->toDateString() : '');
            })->filter()->implode(' | ');

            $isCurrent = ! $c->nextRevision()->exists() && $c->status !== PcContract::STATUS_SUPERSEDED;
            $isCancelled = in_array($c->status, [PcContract::STATUS_CANCELLED, PcContract::STATUS_SUPERSEDED], true);
            $supersededBy = $c->nextRevision?->revision_no ?? '';

            // Compute contract line totals
            $contractQty = 0.0;
            $contractVal = 0.0;
            $totalValExVat = 0.0;

            foreach ($c->lines as $line) {
                $q = (float) $line->qty;
                $p = (float) $line->unit_price;
                $pEx = $vatTreatment === 'INCLUDE' ? ($p / (1 + ($vatRate / 100))) : $p;
                $contractQty += $q;
                $contractVal += ($q * $p);
                $totalValExVat += ($q * $pEx);
            }

            // PDF Keys from files relation
            $approvedPdfKey = $c->files->firstWhere('file_type', 'approved_pdf')?->storage_path ?? '';
            $acceptedPdfKey = $c->files->firstWhere('file_type', 'confirmed_pdf')?->storage_path ?? '';

            $preparedBy = $c->creator?->name ?? ($usersMap[$c->created_by] ?? '');
            $approvedBy = $c->approver?->name ?? ($usersMap[$c->approval_decision_by] ?? '');
            $cancelledBy = $usersMap[$c->cancelled_by] ?? '';

            $supplierResponse = '';
            $supplierRespondedBy = '';
            $supplierRespondedAt = '';
            $supplierQueryReason = '';

            if ($c->status === PcContract::STATUS_CONFIRMED) {
                $supplierResponse = 'ACCEPTED';
                $supplierRespondedBy = $usersMap[$c->confirmed_by] ?? ($c->confirmed_by ?? '');
                $supplierRespondedAt = $c->confirmed_at ? $c->confirmed_at->toISOString() : '';
            } elseif ($c->status === PcContract::STATUS_SUPPLIER_QUERIED) {
                $supplierResponse = 'QUERIED';
                $supplierRespondedBy = $usersMap[$c->vendor_decision_by] ?? ($c->vendor_decision_by ?? '');
                $supplierRespondedAt = $c->vendor_decision_at ? $c->vendor_decision_at->toISOString() : '';
                $supplierQueryReason = $c->vendor_decision_reason ?? '';
            }

            // Write Line Rows
            foreach ($c->lines as $line) {
                $totalLinesCount++;
                $item = $line->fabricItem;
                $plm = $line->plmRef;
                $hs = $line->hsCode;

                $qty = (float) $line->qty;
                $price = (float) $line->unit_price;
                $priceExVat = $vatTreatment === 'INCLUDE' ? ($price / (1 + ($vatRate / 100))) : $price;
                $lineTotal = $qty * $price;
                $lineTotalExVat = $qty * $priceExVat;

                $linesRow = [
                    $c->contract_number,
                    $c->revision_no,
                    $line->line_no,
                    strtoupper($c->status),
                    $isCurrent ? 'true' : 'false',
                    $isCancelled ? 'true' : 'false',
                    $supersededBy,
                    $c->approval_decision_reason ?? '', // revision_reason if any
                    $c->contract_date ? $c->contract_date->toDateString() : '',
                    $seasonCode,
                    $orderTypeCode,
                    $paymentTerm,
                    $deliveryTerm,
                    $transportMode,
                    $vatTreatment,
                    number_format($vatRate, 2, '.', ''),
                    $deliveryDates,
                    $c->internal_remarks ?? '',
                    $vendorCode,
                    $supplierName,
                    $vendor?->supplier_group_code ?? '',
                    $vendor?->country ?? '',
                    $vendor?->contact_person ?? '',
                    $currencyCode,
                    $line->plm_article_name_snapshot ?? $plm?->article_name ?? '',
                    $line->plm_id_snapshot ?? $plm?->plm_id ?? '',
                    $c->deliveries->first()?->lot?->label ?? '1-2',
                    $line->item_number_snapshot ?? $item?->item_number ?? '',
                    $line->item_name_snapshot ?? $item?->item_name ?? '',
                    $item?->composition ?? '',
                    $item?->construction ?? '',
                    $item?->weave ?? $c->fabric_type ?? '',
                    $item?->full_width ?? '',
                    $item?->cut_width ?? '',
                    $item?->weight_gsm ? number_format((float) $item->weight_gsm, 2, '.', '') : '',
                    $item?->process ?? '',
                    $item?->finish ?? '',
                    $line->hs_code_snapshot ?? $hs?->code ?? '',
                    number_format($qty, 4, '.', ''),
                    $line->uom_snapshot ?? $item?->uom_code ?? 'MTR',
                    number_format($price, 4, '.', ''),
                    number_format($priceExVat, 4, '.', ''),
                    number_format($lineTotal, 4, '.', ''),
                    number_format($lineTotalExVat, 4, '.', ''),
                    '', // price_override_reason
                    $line->remarks ?? '',
                    number_format($contractQty, 4, '.', ''),
                    number_format($contractVal, 4, '.', ''),
                    $c->created_at ? $c->created_at->toISOString() : '',
                    $preparedBy,
                    $c->submitted_at ? $c->submitted_at->toISOString() : '',
                    $approvedBy,
                    $c->approval_decision_at ? $c->approval_decision_at->toISOString() : '',
                    $c->sent_to_supplier_at ?? '',
                    $supplierResponse,
                    $supplierRespondedBy,
                    $supplierRespondedAt,
                    $supplierQueryReason,
                    $c->status === PcContract::STATUS_DRAFT && $c->approval_decision_reason ? $c->approval_decision_reason : '',
                    $cancelledBy,
                    $c->cancelled_at ? $c->cancelled_at->toISOString() : '',
                    $c->cancel_reason ?? '',
                    $approvedPdfKey,
                    $acceptedPdfKey,
                    $line->updated_at ? $line->updated_at->toISOString() : $timestampIso,
                    $timestampIso,
                ];
                fputcsv($linesHandle, $linesRow);
            }

            // Write contract header row
            $contractsRow = [
                $c->contract_number,
                $c->revision_no,
                strtoupper($c->status),
                $isCurrent ? 'true' : 'false',
                $c->contract_date ? $c->contract_date->toDateString() : '',
                $seasonCode,
                $orderTypeCode,
                $vendorCode,
                $supplierName,
                $vendor?->supplier_group_code ?? '',
                $currencyCode,
                $paymentTerm,
                $deliveryTerm,
                $transportMode,
                $vatTreatment,
                number_format($vatRate, 2, '.', ''),
                $c->lines->count(),
                number_format($contractQty, 4, '.', ''),
                number_format($contractVal, 4, '.', ''),
                number_format($totalValExVat, 4, '.', ''),
                $c->created_at ? $c->created_at->toISOString() : '',
                $preparedBy,
                $c->submitted_at ? $c->submitted_at->toISOString() : '',
                $approvedBy,
                $c->approval_decision_at ? $c->approval_decision_at->toISOString() : '',
                $c->sent_to_supplier_at ?? '',
                $supplierResponse,
                $supplierRespondedAt,
                '', // revision_reason
                $c->status === PcContract::STATUS_DRAFT && $c->approval_decision_reason ? $c->approval_decision_reason : '',
                $cancelledBy,
                $c->cancelled_at ? $c->cancelled_at->toISOString() : '',
                $c->cancel_reason ?? '',
                $c->updated_at ? $c->updated_at->toISOString() : $timestampIso,
            ];
            fputcsv($contractsHandle, $contractsRow);
        }

        rewind($linesHandle);
        $linesCsvContent = stream_get_contents($linesHandle);
        fclose($linesHandle);

        rewind($contractsHandle);
        $contractsCsvContent = stream_get_contents($contractsHandle);
        fclose($contractsHandle);

        // 3. Generate manifest.json
        $manifest = [
            'generated_at' => $timestampIso,
            'schema_version' => '1.0',
            'counts' => [
                'contracts_total' => $contracts->count(),
                'contracts_by_status' => $statusCounts,
                'lines_total' => $totalLinesCount,
            ],
            'files' => [
                'pc_contract_lines.csv' => [
                    'size_bytes' => strlen($linesCsvContent),
                    'sha256' => hash('sha256', $linesCsvContent),
                ],
                'pc_contracts.csv' => [
                    'size_bytes' => strlen($contractsCsvContent),
                    'sha256' => hash('sha256', $contractsCsvContent),
                ],
            ],
        ];
        $manifestJson = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        // Store to analysis/latest/ and analysis/archive/YYYY-MM-DD/
        $disk = Storage::disk(config('filesystems.default'));

        $targets = [
            'analysis/latest/pc_contract_lines.csv' => $linesCsvContent,
            'analysis/latest/pc_contracts.csv' => $contractsCsvContent,
            'analysis/latest/manifest.json' => $manifestJson,
            "analysis/archive/{$dateStr}/pc_contract_lines.csv" => $linesCsvContent,
            "analysis/archive/{$dateStr}/pc_contracts.csv" => $contractsCsvContent,
            "analysis/archive/{$dateStr}/manifest.json" => $manifestJson,
        ];

        foreach ($targets as $path => $content) {
            $disk->put($path, $content);
        }

        return [
            'date' => $dateStr,
            'contracts_count' => $contracts->count(),
            'lines_count' => $totalLinesCount,
            'manifest' => $manifest,
        ];
    }
}
