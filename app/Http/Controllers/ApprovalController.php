<?php

namespace App\Http\Controllers;

use App\Models\ToleranceAmendmentRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApprovalController extends Controller
{
    public function approveAmendment($requestId)
    {
        $amendmentRequest = ToleranceAmendmentRequest::with('poItem')->findOrFail($requestId);

        if ($amendmentRequest->status !== 'pending') {
            return view('approvals.result', [
                'success' => false,
                'message' => 'This request has already been '.$amendmentRequest->status.'.',
            ]);
        }

        try {
            DB::beginTransaction();

            // 1. Update the request status
            $amendmentRequest->update([
                'status' => 'approved',
                'actioned_at' => now(),
            ]);

            // 2. Update the PO item tolerance ONLY if it's a tolerance amendment
            if ($amendmentRequest->type === 'tolerance') {
                $amendmentRequest->poItem->update([
                    'underdelivery' => $amendmentRequest->new_underdelivery,
                    'overdelivery' => $amendmentRequest->new_overdelivery,
                ]);
            }

            DB::commit();

            $msgType = $amendmentRequest->type === 'partial_shipment' ? 'Partial shipment' : 'Tolerance amendment';

            // Trigger Notification for the Vendor
            try {
                $vendorId = $amendmentRequest->poItem->purchaseOrder->vendor_id;
                $vendorUsers = \App\Models\User::where('vendor_id', $vendorId)->where('role', 'fabric_vendor')->get();
                \Illuminate\Support\Facades\Notification::send($vendorUsers, new \App\Notifications\RequestActionedNotification($amendmentRequest));
            } catch (\Exception $e) {
                \Log::error('Failed to send approval notification to vendor: '.$e->getMessage());
            }

            return view('approvals.result', [
                'success' => true,
                'message' => $msgType.' for Item '.$amendmentRequest->poItem->item_number.' has been approved.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return view('approvals.result', [
                'success' => false,
                'message' => 'Error approving request: '.$e->getMessage(),
            ]);
        }
    }

    public function declineAmendment($requestId)
    {
        $amendmentRequest = ToleranceAmendmentRequest::findOrFail($requestId);

        if ($amendmentRequest->status !== 'pending') {
            return view('approvals.result', [
                'success' => false,
                'message' => 'This request has already been '.$amendmentRequest->status.'.',
            ]);
        }

        $amendmentRequest->update([
            'status' => 'declined',
            'actioned_at' => now(),
        ]);

        // Trigger Notification for the Vendor
        try {
            $vendorId = $amendmentRequest->poItem->purchaseOrder->vendor_id;
            $vendorUsers = \App\Models\User::where('vendor_id', $vendorId)->where('role', 'fabric_vendor')->get();
            \Illuminate\Support\Facades\Notification::send($vendorUsers, new \App\Notifications\RequestActionedNotification($amendmentRequest));
        } catch (\Exception $e) {
            \Log::error('Failed to send decline notification to vendor: '.$e->getMessage());
        }

        $requestType = $amendmentRequest->type === 'partial_shipment' ? 'Partial shipment' : 'Tolerance amendment';

        return view('approvals.result', [
            'success' => true,
            'message' => $requestType.' has been declined.',
        ]);
    }
}
