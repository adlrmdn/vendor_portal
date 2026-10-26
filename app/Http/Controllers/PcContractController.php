<?php

namespace App\Http\Controllers;

use App\Mail\PcContractSentMailable;
use App\Models\Currency;
use App\Models\DeliveryTerm;
use App\Models\HsCode;
use App\Models\Lot;
use App\Models\OrderType;
use App\Models\PcAuditLog;
use App\Models\PcContract;
use App\Models\PcFabricItem;
use App\Models\PcPlmRef;
use App\Models\Season;
use App\Models\ToleranceTemplate;
use App\Models\TransportMode;
use App\Models\User;
use App\Models\VatTreatment;
use App\Models\Vendor;
use App\Services\PcContractApprovalService;
use App\Services\PcContractNumberService;
use App\Services\PcContractValidator;
use App\Services\TotpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class PcContractController extends Controller
{
    public function __construct(
        private PcContractValidator $validator,
        private PcContractApprovalService $approvalService,
        private PcContractNumberService $numberService,
        private TotpService $totp,
    ) {}

    public function index(Request $request)
    {
        $query = PcContract::with('vendor')->withCount('lines');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($vendorId = $request->input('vendor_id')) {
            $query->where('vendor_id', $vendorId);
        }
        if ($search = $request->input('search')) {
            $query->search($search);
        }

        $perPage = (int) $request->get('per_page', 25);
        if (! in_array($perPage, [10, 25, 50])) {
            $perPage = 25;
        }

        $contracts = $query->orderBy('created_at', 'desc')->paginate($perPage)->appends($request->all());
        $vendors = Vendor::where('type', 'fabric')->where('is_active', true)->orderBy('name')->get();

        return view('admin.pc.contracts.index', compact('contracts', 'vendors', 'status', 'vendorId', 'search', 'perPage'));
    }

    public function create()
    {
        return view('admin.pc.contracts.create', $this->formLookups());
    }

    public function store(Request $request)
    {
        $data = $this->validatedHeader($request);
        $vendor = Vendor::findOrFail($data['vendor_id']);

        $contract = new PcContract($data);
        $contract->created_by = Auth::id();
        $currencyId = $data['currency_id'] ?? null;
        if (! $currencyId && $vendor->currency_code) {
            $code = strtoupper($vendor->currency_code) === 'CNY' ? 'RMB' : $vendor->currency_code;
            $currencyId = Currency::where('code', $code)->value('id');
        }
        $contract->currency_id = $currencyId;
        if ($currencyId) {
            $curr = Currency::find($currencyId);
            if ($curr && $vendor->currency_code !== $curr->code) {
                $vendor->currency_code = $curr->code;
                $vendor->save();
            }
        }
        $contract->payment_term_id = $vendor->payment_term_id;
        $contract->status = PcContract::STATUS_DRAFT;
        $contract->save();

        $this->syncLinesAndDeliveries($contract, $request->input('lines', []), $request->input('deliveries', []));

        $warnings = $this->validator->validateForSave($request->all());

        PcAuditLog::record([
            'actor' => Auth::user()->email,
            'actor_role' => Auth::user()->role,
            'event_type' => 'contract.create',
            'entity_type' => 'pc_contract',
            'entity_id' => $contract->id,
            'entity_label' => $contract->contract_number ?? "draft:{$contract->id}",
        ]);

        return redirect()->route('admin.pc.contracts.edit', $contract)
            ->with('success', 'Draft saved.')
            ->with('warnings', $warnings);
    }

    public function edit(PcContract $contract)
    {
        if (! $contract->isEditableByPreparer()) {
            return redirect()->route('admin.pc.contracts.show', $contract)
                ->with('error', 'This contract can no longer be edited as draft.');
        }

        $contract->load(['lines.fabricItem', 'lines.plmRef', 'lines.hsCode', 'deliveries.lot']);

        return view('admin.pc.contracts.edit', $this->formLookups() + ['contract' => $contract]);
    }

    public function update(Request $request, PcContract $contract)
    {
        if (! $contract->isEditableByPreparer()) {
            return redirect()->route('admin.pc.contracts.show', $contract)
                ->with('error', 'This contract can no longer be edited as draft.');
        }

        $data = $this->validatedHeader($request);
        $vendor = Vendor::findOrFail($data['vendor_id']);

        $contract->fill($data);
        $currencyId = $data['currency_id'] ?? null;
        if (! $currencyId && $vendor->currency_code) {
            $code = strtoupper($vendor->currency_code) === 'CNY' ? 'RMB' : $vendor->currency_code;
            $currencyId = Currency::where('code', $code)->value('id');
        }
        $contract->currency_id = $currencyId;
        if ($currencyId) {
            $curr = Currency::find($currencyId);
            if ($curr && $vendor->currency_code !== $curr->code) {
                $vendor->currency_code = $curr->code;
                $vendor->save();
            }
        }
        $contract->payment_term_id = $vendor->payment_term_id;
        $contract->save();

        $this->syncLinesAndDeliveries($contract, $request->input('lines', []), $request->input('deliveries', []));

        $warnings = $this->validator->validateForSave($request->all());

        PcAuditLog::record([
            'actor' => Auth::user()->email,
            'actor_role' => Auth::user()->role,
            'event_type' => 'contract.update',
            'entity_type' => 'pc_contract',
            'entity_id' => $contract->id,
            'entity_label' => $contract->contract_number ?? "draft:{$contract->id}",
        ]);

        return redirect()->route('admin.pc.contracts.edit', $contract)
            ->with('success', 'Draft saved.')
            ->with('warnings', $warnings);
    }

    public function show(PcContract $contract)
    {
        $contract->load([
            'vendor', 'currency', 'paymentTerm', 'deliveryTerm', 'transportMode', 'vatTreatment', 'season', 'orderType',
            'lines.fabricItem', 'lines.plmRef', 'lines.hsCode', 'deliveries.lot',
            'notes', 'tolerances', 'extraNotes.author', 'vendorResponses.respondedBy', 'files', 'creator', 'approver',
        ]);

        $isApprover = Auth::user()->isAdmin() || PcAdminController::isPcApproverUser(Auth::user());

        return view('admin.pc.contracts.show', compact('contract', 'isApprover'));
    }

    /**
     * Submit draft for internal approval.
     * Transitions: draft -> pending_approval.
     * Contract number is officially issued here.
     */
    public function submit(PcContract $contract)
    {
        if (! $contract->isEditableByPreparer()) {
            return redirect()->route('admin.pc.contracts.show', $contract)->with('error', 'Only a draft can be submitted.');
        }

        $contract->load(['vendor', 'currency', 'season', 'deliveries', 'lines.fabricItem']);

        $errors = $this->validator->validateForSubmit($contract);
        if (! empty($errors)) {
            return redirect()->route('admin.pc.contracts.edit', $contract)->with('errors', $errors);
        }

        if (! $contract->contract_date) {
            $contract->contract_date = now()->toDateString();
        }

        // Issue official number on submit (if not already set via revision)
        if (! $contract->contract_number) {
            $contract->contract_number = $this->numberService->issue();
        }

        $contract->status = PcContract::STATUS_PENDING_APPROVAL;
        $contract->submitted_at = now();
        $contract->submitted_by = Auth::id();
        $contract->save();

        PcAuditLog::record([
            'actor' => Auth::user()->email,
            'actor_role' => Auth::user()->role,
            'event_type' => 'contract.submit',
            'entity_type' => 'pc_contract',
            'entity_id' => $contract->id,
            'entity_label' => $contract->contract_number,
        ]);

        return redirect()->route('admin.pc.contracts.show', $contract)
            ->with('success', "Submitted for internal approval. Contract number: {$contract->contract_number}");
    }

    /**
     * Internal approval by Approver (Aslam) with TOTP.
     * Transitions: pending_approval -> approved.
     */
    public function approve(Request $request, PcContract $contract)
    {
        if (! $contract->isAwaitingApproval()) {
            return redirect()->route('admin.pc.contracts.show', $contract)->with('error', 'This contract is not awaiting approval.');
        }

        $user = Auth::user();
        if (! ($user->isAdmin() || PcAdminController::isPcApproverUser($user))) {
            abort(403, 'You are not a configured PC Approver.');
        }

        if ($contract->created_by === $user->id) {
            return redirect()->route('admin.pc.contracts.show', $contract)
                ->with('error', 'Separation of duties: you prepared this contract and cannot also approve it.');
        }

        $data = $request->validate(['code' => 'required|string|size:6']);
        if (! $user->totp_secret || ! $this->totp->verifyTotpCode($user->totp_secret, $data['code'])) {
            return redirect()->route('admin.pc.contracts.show', $contract)->with('error', 'That authenticator code did not match.');
        }

        $this->approvalService->approve($contract, $user);

        PcAuditLog::record([
            'actor' => $user->email,
            'actor_role' => $user->role,
            'event_type' => 'contract.approve',
            'entity_type' => 'pc_contract',
            'entity_id' => $contract->id,
            'entity_label' => $contract->fresh()->contract_number,
        ]);

        return redirect()->route('admin.pc.contracts.show', $contract)->with('success', 'Contract approved. Stored PDF is ready to be sent to the supplier.');
    }

    /**
     * Rejection by Approver back to draft.
     * Transitions: pending_approval -> draft.
     */
    public function reject(Request $request, PcContract $contract)
    {
        if (! $contract->isAwaitingApproval()) {
            return redirect()->route('admin.pc.contracts.show', $contract)->with('error', 'This contract is not awaiting approval.');
        }

        $user = Auth::user();
        if (! ($user->isAdmin() || PcAdminController::isPcApproverUser($user))) {
            abort(403, 'You are not a configured PC Approver.');
        }

        $data = $request->validate(['reason' => 'required|string|min:10', 'code' => 'required|string|size:6']);
        if (! $user->totp_secret || ! $this->totp->verifyTotpCode($user->totp_secret, $data['code'])) {
            return redirect()->route('admin.pc.contracts.show', $contract)->with('error', 'That authenticator code did not match.');
        }

        $contract->status = PcContract::STATUS_DRAFT;
        $contract->approval_decision = 'rejected';
        $contract->approval_decision_at = now();
        $contract->approval_decision_by = $user->id;
        $contract->approval_decision_reason = $data['reason'];
        $contract->save();

        PcAuditLog::record([
            'actor' => $user->email,
            'actor_role' => $user->role,
            'event_type' => 'contract.reject',
            'entity_type' => 'pc_contract',
            'entity_id' => $contract->id,
            'entity_label' => $contract->contract_number ?? "draft:{$contract->id}",
            'note' => $data['reason'],
        ]);

        return redirect()->route('admin.pc.contracts.show', $contract)->with('success', 'Rejected back to draft.');
    }

    /**
     * Send approved contract to supplier for review.
     * Transitions: approved -> sent_to_supplier.
     */
    public function sendToSupplier(PcContract $contract)
    {
        if (! $contract->isApproved()) {
            return redirect()->route('admin.pc.contracts.show', $contract)->with('error', 'Only an approved contract can be sent to the supplier.');
        }

        // PC-A01: PDF must already be safely stored
        $pdfFile = $contract->files()->where('file_type', 'contract_pdf')->latest('generated_at')->first();
        if (! $pdfFile || ! Storage::disk($pdfFile->disk)->exists($pdfFile->path)) {
            return redirect()->route('admin.pc.contracts.show', $contract)
                ->with('error', 'PC-A01: Approved PDF is not stored yet — cannot send.');
        }

        // PC-A02: Vendor must have an active portal account
        $vendorUsers = User::where('vendor_id', $contract->vendor_id)->where('role', 'fabric_vendor')->get();
        if ($vendorUsers->isEmpty()) {
            return redirect()->route('admin.pc.contracts.show', $contract)
                ->with('error', 'PC-A02: This vendor has no Vendor Portal login yet — request onboarding before sending.');
        }

        $contract->status = PcContract::STATUS_SENT_TO_SUPPLIER;
        $contract->save();

        PcAuditLog::record([
            'actor' => Auth::user()->email,
            'actor_role' => Auth::user()->role,
            'event_type' => 'contract.send_to_supplier',
            'entity_type' => 'pc_contract',
            'entity_id' => $contract->id,
            'entity_label' => $contract->contract_number,
        ]);

        try {
            foreach ($vendorUsers as $vUser) {
                Mail::to($vUser->email)->send(new PcContractSentMailable($contract));
            }
        } catch (\Throwable $e) {
            return redirect()->route('admin.pc.contracts.show', $contract)
                ->with('warning', 'Contract moved to Sent, but email notification failed: '.$e->getMessage());
        }

        return redirect()->route('admin.pc.contracts.show', $contract)->with('success', 'Contract sent to supplier for review.');
    }

    public function cancel(Request $request, PcContract $contract)
    {
        $user = Auth::user();
        if (! $contract->canBeCancelledBy($user)) {
            abort(403);
        }

        $data = $request->validate(['reason' => 'required|string|min:5']);

        $contract->status = PcContract::STATUS_CANCELLED;
        $contract->cancelled_at = now();
        $contract->cancelled_by = $user->id;
        $contract->cancel_reason = $data['reason'];
        $contract->save();

        PcAuditLog::record([
            'actor' => $user->email,
            'actor_role' => $user->role,
            'event_type' => 'contract.cancel',
            'entity_type' => 'pc_contract',
            'entity_id' => $contract->id,
            'entity_label' => $contract->contract_number ?? "draft:{$contract->id}",
            'note' => $data['reason'],
        ]);

        return redirect()->route('admin.pc.contracts.show', $contract)->with('success', 'Contract cancelled.');
    }

    public function revise(Request $request, PcContract $contract)
    {
        if (! $contract->canBeRevised()) {
            return redirect()->route('admin.pc.contracts.show', $contract)
                ->with('error', 'This contract is not in a state that can be revised.');
        }

        $reason = $request->input('reason', '');
        $contract->load('lines');

        $revision = new PcContract($contract->only([
            'vendor_id', 'fabric_type', 'currency_id', 'payment_term_id', 'delivery_term_id',
            'transport_mode_id', 'vat_treatment_id', 'vat_rate', 'season_id', 'order_type_id',
        ]));
        $revision->contract_number = $contract->contract_number;
        $revision->revision_no = $contract->revision_no + 1;
        $revision->previous_revision_id = $contract->id;
        $revision->status = PcContract::STATUS_DRAFT;
        $revision->created_by = Auth::id();
        $revision->contract_date = now()->toDateString();
        $revision->internal_remarks = $reason ? "Revision reason: {$reason}" : null;
        $revision->save();

        foreach ($contract->lines as $line) {
            $revision->lines()->create($line->only([
                'line_no', 'plm_ref_id', 'plm_id_snapshot', 'plm_article_name_snapshot',
                'fabric_item_id', 'item_number_snapshot', 'item_name_snapshot', 'uom_snapshot',
                'hs_code_id', 'hs_code_snapshot', 'qty', 'unit_price', 'remarks',
            ]));
        }

        // Copy contract-level deliveries
        foreach ($contract->deliveries as $del) {
            $revision->deliveries()->create([
                'contract_id' => $revision->id,
                'contract_line_id' => $del->contract_line_id ?? $revision->id,
                'lot_id' => $del->lot_id,
                'delivery_qty' => $del->delivery_qty ?? 0,
                'eta_date' => $del->eta_date,
                'remarks' => $del->remarks,
            ]);
        }

        PcAuditLog::record([
            'actor' => Auth::user()->email,
            'actor_role' => Auth::user()->role,
            'event_type' => 'contract.revise',
            'entity_type' => 'pc_contract',
            'entity_id' => $revision->id,
            'entity_label' => $revision->contract_number,
            'note' => $reason ?: "Revision of {$contract->id}",
        ]);

        return redirect()->route('admin.pc.contracts.edit', $revision)->with('success', 'New revision created as draft.');
    }

    public function pdf(PcContract $contract)
    {
        $file = $contract->files()->where('file_type', 'contract_pdf')->latest('generated_at')->first();
        if ($file && Storage::disk($file->disk)->exists($file->path)) {
            return response(Storage::disk($file->disk)->get($file->path), 200, [
                'Content-Type' => $file->mime_type,
                'Content-Disposition' => 'inline; filename="'.$file->original_filename.'"',
            ]);
        }

        // On-the-fly preview for drafts or pending approval contracts
        $bytes = app(\App\Services\PcContractPdfService::class)->render($contract);
        $filename = ($contract->contract_number ?: "draft-{$contract->id}").'.pdf';

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    private function formLookups(): array
    {
        return [
            'vendors' => Vendor::where('type', 'fabric')->where('is_active', true)->with('paymentTerm')->orderBy('name')->get(),
            'currencies' => Currency::orderBy('code')->get(),
            'seasons' => Season::active()->orderBy('sort_key')->get(),
            'orderTypes' => OrderType::active()->orderBy('code')->get(),
            'transportModes' => TransportMode::active()->orderBy('code')->get(),
            'deliveryTerms' => DeliveryTerm::active()->orderBy('code')->get(),
            'vatTreatments' => VatTreatment::active()->orderBy('code')->get(),
            'fabricTypes' => ToleranceTemplate::select('fabric_type')->distinct()->pluck('fabric_type'),
            'hsCodes' => HsCode::active()->orderBy('code')->get(),
            'lots' => Lot::active()->orderBy('code')->get(),
        ];
    }

    private function validatedHeader(Request $request): array
    {
        return $request->validate([
            'vendor_id' => 'required|uuid|exists:vendors,id',
            'currency_id' => 'nullable|uuid|exists:currencies,id',
            'fabric_type' => 'nullable|string|max:10',
            'contract_date' => 'nullable|date',
            'season_id' => 'nullable|uuid|exists:seasons,id',
            'order_type_id' => 'nullable|uuid|exists:order_types,id',
            'transport_mode_id' => 'nullable|uuid|exists:transport_modes,id',
            'delivery_term_id' => 'nullable|uuid|exists:delivery_terms,id',
            'vat_treatment_id' => 'nullable|uuid|exists:vat_treatments,id',
            'vat_rate' => 'nullable|numeric|min:0|max:100',
            'internal_remarks' => 'nullable|string',
        ]);
    }

    private function syncLinesAndDeliveries(PcContract $contract, array $linesInput, array $deliveriesInput = []): void
    {
        $contract->lines()->delete();
        $contract->deliveries()->delete();

        $lineNo = 0;
        foreach ($linesInput as $lineData) {
            if (! empty($lineData['delete'])) {
                continue;
            }
            if (empty($lineData['fabric_item_id']) && empty($lineData['plm_ref_id'])) {
                continue;
            }

            $lineNo++;
            $fabricItem = ! empty($lineData['fabric_item_id']) ? PcFabricItem::find($lineData['fabric_item_id']) : null;
            $plmRef = ! empty($lineData['plm_ref_id']) ? PcPlmRef::find($lineData['plm_ref_id']) : null;
            $hsCode = ! empty($lineData['hs_code_id']) ? HsCode::find($lineData['hs_code_id']) : null;

            $contract->lines()->create([
                'line_no' => $lineNo,
                'plm_ref_id' => $plmRef?->id,
                'plm_id_snapshot' => $plmRef?->plm_id,
                'plm_article_name_snapshot' => $plmRef?->article_name,
                'fabric_item_id' => $fabricItem?->id,
                'item_number_snapshot' => $fabricItem?->item_number ?? '',
                'item_name_snapshot' => $fabricItem?->item_name,
                'uom_snapshot' => $fabricItem?->uom_code,
                'hs_code_id' => $hsCode?->id,
                'hs_code_snapshot' => $hsCode?->code,
                'qty' => $lineData['qty'] ?? 0,
                'unit_price' => $lineData['unit_price'] ?? 0,
                'remarks' => $lineData['remarks'] ?? null,
            ]);
        }

        // Contract-level fabric delivery schedules (Lot + ETA Date)
        foreach ($deliveriesInput as $dData) {
            if (! empty($dData['delete']) || empty($dData['lot_id'])) {
                continue;
            }
            $contract->deliveries()->create([
                'contract_id' => $contract->id,
                'contract_line_id' => $contract->id, // default to contract id
                'lot_id' => $dData['lot_id'],
                'delivery_qty' => $dData['delivery_qty'] ?? 0,
                'eta_date' => $dData['eta_date'] ?? null,
                'remarks' => $dData['remarks'] ?? null,
            ]);
        }
    }
}
