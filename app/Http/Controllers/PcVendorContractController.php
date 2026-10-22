<?php

namespace App\Http\Controllers;

use App\Models\PcAuditLog;
use App\Models\PcContract;
use App\Models\PcContractVendorResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * PC's own vendor-facing controller — kept separate from the 989-line
 * VendorController, matching how PC already gets its own Pc*Controllers
 * on the admin side. Every query is scoped by Auth::user()->vendor_id;
 * findOrFail 404s for access denial (the scoping clause itself is the
 * authorization, same idiom as VendorController::viewPurchaseOrder).
 */
class PcVendorContractController extends Controller
{
    public function index(Request $request)
    {
        $vendorId = Auth::user()->vendor_id;

        $contracts = PcContract::forVendor($vendorId)
            ->whereIn('status', ['pending_vendor_review', 'pending_final_approval', 'confirmed', 'cancelled', 'superseded'])
            ->orderBy('created_at', 'desc')
            ->paginate(25);

        return view('vendor.pc.contracts-index', compact('contracts'));
    }

    public function show(PcContract $contract)
    {
        $vendorId = Auth::user()->vendor_id;
        abort_unless($contract->vendor_id === $vendorId, 404);

        $contract->load([
            'vendor', 'currency', 'paymentTerm', 'deliveryTerm', 'transportMode', 'vatTreatment', 'season', 'orderType',
            'lines.fabricItem', 'lines.plmRef', 'lines.hsCode', 'lines.deliveries.lot',
            'notes', 'tolerances', 'extraNotes',
        ]);

        return view('vendor.pc.contract-view', compact('contract'));
    }

    public function accept(PcContract $contract)
    {
        $vendorId = Auth::user()->vendor_id;
        abort_unless($contract->vendor_id === $vendorId, 404);

        $blocked = false;
        DB::transaction(function () use ($contract, &$blocked) {
            $contract = PcContract::where('id', $contract->id)->lockForUpdate()->first();
            if ($contract->status !== 'pending_vendor_review') {
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

            $contract->status = 'pending_final_approval';
            $contract->vendor_decision = 'accepted';
            $contract->vendor_decision_at = now();
            $contract->vendor_decision_by = Auth::id();
            $contract->vendor_decision_reason = null;
            $contract->save();
        });

        if ($blocked) {
            return redirect()->route('vendor.pc.contracts.show', $contract)->with('error', 'This contract is no longer awaiting your review.');
        }

        PcAuditLog::record([
            'actor' => Auth::user()->email,
            'actor_role' => Auth::user()->role,
            'event_type' => 'contract.vendor_accept',
            'entity_type' => 'pc_contract',
            'entity_id' => $contract->id,
            'entity_label' => $contract->contract_number ?? "draft:{$contract->id}",
        ]);

        return redirect()->route('vendor.pc.contracts.show', $contract)->with('success', 'Accepted — sent for final internal approval.');
    }

    public function query(Request $request, PcContract $contract)
    {
        $vendorId = Auth::user()->vendor_id;
        abort_unless($contract->vendor_id === $vendorId, 404);

        $data = $request->validate(['reason' => 'required|string|min:10']);

        $blocked = false;
        DB::transaction(function () use ($contract, $data, &$blocked) {
            $contract = PcContract::where('id', $contract->id)->lockForUpdate()->first();
            if ($contract->status !== 'pending_vendor_review') {
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

            $contract->status = 'draft';
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
            'entity_label' => $contract->contract_number ?? "draft:{$contract->id}",
            'note' => $data['reason'],
        ]);

        return redirect()->route('vendor.pc.contracts.index')->with('success', 'Query sent back to the preparer.');
    }

    public function pdf(PcContract $contract)
    {
        $vendorId = Auth::user()->vendor_id;
        abort_unless($contract->vendor_id === $vendorId, 404);

        $file = $contract->files()->where('file_type', 'contract_pdf')->latest('generated_at')->first();
        if (! $file) {
            abort(404, 'No PDF has been generated for this contract yet.');
        }

        return response(Storage::disk($file->disk)->get($file->path), 200, [
            'Content-Type' => $file->mime_type,
            'Content-Disposition' => 'inline; filename="'.$file->original_filename.'"',
        ]);
    }
}
