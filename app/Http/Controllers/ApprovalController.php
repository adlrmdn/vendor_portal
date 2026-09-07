<?php

namespace App\Http\Controllers;

use App\Models\ToleranceAmendmentRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

class ApprovalController extends Controller
{
    // --- No-login, signed-URL entry points (email links) -------------------
    //
    // These GET links are the ones embedded in the approval email, so they
    // get hit by link-prescanning bots (Outlook Safe Links, corporate mail
    // gateways, spam filters) before a human ever opens the message. A GET
    // that mutated immediately meant the DECLINE button could get "clicked"
    // by a scanner and silently reject a vendor's request. Both approve and
    // decline now render a confirm page on GET; the actual mutation happens
    // on the signed POST below.

    public function approveAmendment($requestId)
    {
        $amendmentRequest = ToleranceAmendmentRequest::with('poItem.purchaseOrder')->findOrFail($requestId);

        if ($amendmentRequest->status !== 'pending') {
            return view('approvals.result', [
                'success' => false,
                'message' => 'This request has already been '.$amendmentRequest->status.'.',
            ]);
        }

        return view('approvals.confirm', [
            'amendmentRequest' => $amendmentRequest,
            'action' => 'approve',
            'submitUrl' => url(URL::signedRoute('tolerance.approve.submit', ['request' => $amendmentRequest->id], absolute: false)),
        ]);
    }

    public function approveAmendmentSubmit(Request $request, $requestId)
    {
        $amendmentRequest = ToleranceAmendmentRequest::with('poItem.purchaseOrder')->findOrFail($requestId);

        $result = $this->applyApproval($amendmentRequest);

        return view('approvals.result', [
            'success' => $result['ok'],
            'message' => $result['message'],
        ]);
    }

    public function declineAmendment($requestId)
    {
        $amendmentRequest = ToleranceAmendmentRequest::with('poItem.purchaseOrder')->findOrFail($requestId);

        if ($amendmentRequest->status !== 'pending') {
            return view('approvals.result', [
                'success' => false,
                'message' => 'This request has already been '.$amendmentRequest->status.'.',
            ]);
        }

        return view('approvals.confirm', [
            'amendmentRequest' => $amendmentRequest,
            'action' => 'decline',
            'submitUrl' => url(URL::signedRoute('tolerance.decline.submit', ['request' => $amendmentRequest->id], absolute: false)),
        ]);
    }

    public function declineAmendmentSubmit(Request $request, $requestId)
    {
        $amendmentRequest = ToleranceAmendmentRequest::with('poItem.purchaseOrder')->findOrFail($requestId);

        $result = $this->applyDecline($amendmentRequest);

        return view('approvals.result', [
            'success' => $result['ok'],
            'message' => $result['message'],
        ]);
    }

    // --- In-app entry points (fabric admin Approvals tab) -------------------

    public function approveInApp(Request $request, $requestId)
    {
        $this->authorizeAdmin();

        $amendmentRequest = ToleranceAmendmentRequest::with('poItem.purchaseOrder')->findOrFail($requestId);
        $result = $this->applyApproval($amendmentRequest);

        return redirect()->route('admin.approvals')->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function declineInApp(Request $request, $requestId)
    {
        $this->authorizeAdmin();

        $amendmentRequest = ToleranceAmendmentRequest::with('poItem.purchaseOrder')->findOrFail($requestId);
        $result = $this->applyDecline($amendmentRequest);

        return redirect()->route('admin.approvals')->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    private function authorizeAdmin(): void
    {
        if (! in_array(Auth::user()->role ?? '', ['admin', 'fabric_admin'], true)) {
            abort(403, 'Unauthorized access.');
        }
    }

    // --- Shared transition logic ---------------------------------------------

    /**
     * @return array{ok: bool, message: string}
     */
    private function applyApproval(ToleranceAmendmentRequest $amendmentRequest): array
    {
        if ($amendmentRequest->status !== 'pending') {
            return ['ok' => false, 'message' => 'This request has already been '.$amendmentRequest->status.'.'];
        }

        try {
            DB::beginTransaction();

            $amendmentRequest->update([
                'status' => 'approved',
                'actioned_at' => now(),
            ]);

            // Update the PO item tolerance ONLY if it's a tolerance amendment
            if ($amendmentRequest->type === 'tolerance') {
                $amendmentRequest->poItem->update([
                    'underdelivery' => $amendmentRequest->new_underdelivery,
                    'overdelivery' => $amendmentRequest->new_overdelivery,
                ]);
            } elseif ($amendmentRequest->type === 'partial_shipment') {
                // Execute the split immediately. Approval used to only flip the
                // status to 'approved' and leave it to the vendor to come back
                // and press a separate "Execute Partial Shipment" button — in
                // practice vendors routinely never did, leaving the item stuck
                // at its old status indefinitely. 'implemented' also blocks the
                // manual button/endpoint from re-running the split afterward.
                $amendmentRequest->poItem->splitToPartialShipment();
                $amendmentRequest->update(['status' => 'implemented']);
            }

            DB::commit();

            $msgType = $amendmentRequest->type === 'partial_shipment' ? 'Partial shipment' : 'Tolerance amendment';

            $this->notifyVendor($amendmentRequest);

            return ['ok' => true, 'message' => $msgType.' for Item '.$amendmentRequest->poItem->item_number.' has been approved.'];
        } catch (\Exception $e) {
            DB::rollBack();

            return ['ok' => false, 'message' => 'Error approving request: '.$e->getMessage()];
        }
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function applyDecline(ToleranceAmendmentRequest $amendmentRequest): array
    {
        if ($amendmentRequest->status !== 'pending') {
            return ['ok' => false, 'message' => 'This request has already been '.$amendmentRequest->status.'.'];
        }

        $amendmentRequest->update([
            'status' => 'declined',
            'actioned_at' => now(),
        ]);

        $this->notifyVendor($amendmentRequest);

        $requestType = $amendmentRequest->type === 'partial_shipment' ? 'Partial shipment' : 'Tolerance amendment';

        return ['ok' => true, 'message' => $requestType.' has been declined.'];
    }

    private function notifyVendor(ToleranceAmendmentRequest $amendmentRequest): void
    {
        try {
            $vendorId = $amendmentRequest->poItem->purchaseOrder->vendor_id;
            $vendorUsers = \App\Models\User::where('vendor_id', $vendorId)->where('role', 'fabric_vendor')->get();
            \Illuminate\Support\Facades\Notification::send($vendorUsers, new \App\Notifications\RequestActionedNotification($amendmentRequest));
        } catch (\Exception $e) {
            \Log::error('Failed to send approval decision notification to vendor: '.$e->getMessage());
        }
    }
}
