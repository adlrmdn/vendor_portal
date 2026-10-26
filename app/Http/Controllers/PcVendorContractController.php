<?php

namespace App\Http\Controllers;

use App\Models\PcAuditLog;
use App\Models\PcContract;
use App\Models\PcContractVendorResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PcVendorContractController extends Controller
{
    public function index(Request $request)
    {
        $vendorId = Auth::user()->vendor_id;
        $tab = $request->input('tab', 'new');

        $query = PcContract::forVendor($vendorId)
            ->whereIn('status', [
                PcContract::STATUS_SENT_TO_SUPPLIER,
                PcContract::STATUS_CONFIRMED,
                PcContract::STATUS_SUPPLIER_QUERIED,
                PcContract::STATUS_CANCELLED,
                PcContract::STATUS_SUPERSEDED,
            ]);

        $counts = [
            'new' => (clone $query)->where('status', PcContract::STATUS_SENT_TO_SUPPLIER)->count(),
            'confirmed' => (clone $query)->where('status', PcContract::STATUS_CONFIRMED)->count(),
            'queried' => (clone $query)->where('status', PcContract::STATUS_SUPPLIER_QUERIED)->count(),
            'history' => (clone $query)->whereIn('status', [PcContract::STATUS_CANCELLED, PcContract::STATUS_SUPERSEDED])->count(),
            'all' => (clone $query)->count(),
        ];

        if ($tab === 'new') {
            $query->where('status', PcContract::STATUS_SENT_TO_SUPPLIER);
        } elseif ($tab === 'confirmed') {
            $query->where('status', PcContract::STATUS_CONFIRMED);
        } elseif ($tab === 'queried') {
            $query->where('status', PcContract::STATUS_SUPPLIER_QUERIED);
        } elseif ($tab === 'history') {
            $query->whereIn('status', [PcContract::STATUS_CANCELLED, PcContract::STATUS_SUPERSEDED]);
        }

        $contracts = $query->orderBy('created_at', 'desc')->paginate(25)->appends($request->all());

        return view('vendor.pc.contracts-index', compact('contracts', 'tab', 'counts'));
    }

    public function show(PcContract $contract)
    {
        $vendorId = Auth::user()->vendor_id;
        abort_unless($contract->vendor_id === $vendorId, 404);

        $contract->load([
            'vendor', 'currency', 'paymentTerm', 'deliveryTerm', 'transportMode', 'vatTreatment', 'season', 'orderType',
            'lines.fabricItem', 'lines.plmRef', 'lines.hsCode', 'deliveries.lot',
            'notes', 'tolerances', 'extraNotes',
        ]);

        return view('vendor.pc.contract-view', compact('contract'));
    }

    public function accept(Request $request, PcContract $contract)
    {
        $vendorId = Auth::user()->vendor_id;
        abort_unless($contract->vendor_id === $vendorId, 404);

        $blocked = false;
        DB::transaction(function () use ($contract, &$blocked) {
            $contract = PcContract::where('id', $contract->id)->lockForUpdate()->first();
            if ($contract->status !== PcContract::STATUS_SENT_TO_SUPPLIER) {
                $blocked = true;

                return;
            }

            PcContractVendorResponse::create([
                'contract_id' => $contract->id,
                'vendor_id' => $contract->vendor_id,
                'decision' => 'accepted',
                'responded_by' => Auth::id(),
                'responded_by_name_snapshot' => Auth::user()->name,
            ]);

            $contract->status = PcContract::STATUS_CONFIRMED;
            $contract->vendor_decision = 'accepted';
            $contract->vendor_decision_at = now();
            $contract->vendor_decision_by = Auth::id();
            $contract->confirmed_at = now();
            $contract->confirmed_by = Auth::id();
            $contract->save();
        });

        if ($blocked) {
            return redirect()->route('vendor.pc.contracts.show', $contract)->with('error', 'This contract is no longer awaiting your review.');
        }

        try {
            app(\App\Services\PcContractPdfService::class)->generate($contract->fresh());
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to update confirmed PDF with vendor signature: ' . $e->getMessage());
        }

        PcAuditLog::record([
            'actor' => Auth::user()->email,
            'actor_role' => Auth::user()->role,
            'event_type' => 'contract.vendor_accept',
            'entity_type' => 'pc_contract',
            'entity_id' => $contract->id,
            'entity_label' => $contract->contract_number ?? "contract:{$contract->id}",
            'note' => 'Accepted by vendor. IP: '.$request->ip(),
        ]);

        return redirect()->route('vendor.pc.contracts.show', $contract)->with('success', 'Contract accepted. Thank you.');
    }

    public function query(Request $request, PcContract $contract)
    {
        $vendorId = Auth::user()->vendor_id;
        abort_unless($contract->vendor_id === $vendorId, 404);

        $data = $request->validate(['reason' => 'required|string|min:10']);

        $blocked = false;
        DB::transaction(function () use ($contract, $data, &$blocked) {
            $contract = PcContract::where('id', $contract->id)->lockForUpdate()->first();
            if ($contract->status !== PcContract::STATUS_SENT_TO_SUPPLIER) {
                $blocked = true;

                return;
            }

            PcContractVendorResponse::create([
                'contract_id' => $contract->id,
                'vendor_id' => $contract->vendor_id,
                'decision' => 'queried',
                'reason' => $data['reason'],
                'responded_by' => Auth::id(),
                'responded_by_name_snapshot' => Auth::user()->name,
            ]);

            $contract->status = PcContract::STATUS_SUPPLIER_QUERIED;
            $contract->vendor_decision = 'queried';
            $contract->vendor_decision_at = now();
            $contract->vendor_decision_by = Auth::id();
            $contract->vendor_decision_reason = $data['reason'];
            $contract->save();
        });

        if ($blocked) {
            return redirect()->route('vendor.pc.contracts.show', $contract)->with('error', 'This contract is no longer awaiting your review.');
        }

        PcAuditLog::record([
            'actor' => Auth::user()->email,
            'actor_role' => Auth::user()->role,
            'event_type' => 'contract.vendor_query',
            'entity_type' => 'pc_contract',
            'entity_id' => $contract->id,
            'entity_label' => $contract->contract_number ?? "contract:{$contract->id}",
            'note' => $data['reason'],
        ]);

        return redirect()->route('vendor.pc.contracts.index')->with('success', 'Query sent to PT Mega Perintis.');
    }

    public function pdf(PcContract $contract)
    {
        $vendorId = Auth::user()->vendor_id;
        abort_unless($contract->vendor_id === $vendorId, 404);

        $file = $contract->files()->where('file_type', 'contract_pdf')->latest('generated_at')->first();
        if ($file && Storage::disk($file->disk)->exists($file->path)) {
            return response(Storage::disk($file->disk)->get($file->path), 200, [
                'Content-Type' => $file->mime_type,
                'Content-Disposition' => 'inline; filename="'.$file->original_filename.'"',
            ]);
        }

        $bytes = app(\App\Services\PcContractPdfService::class)->render($contract);
        $filename = ($contract->contract_number ?: "contract-{$contract->id}").'.pdf';

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}
