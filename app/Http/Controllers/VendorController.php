<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PoItem;
use App\Models\Roll;
use App\Models\PackingSlip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use Smalot\PdfParser\Parser as PdfParser;
use App\Models\Setting;
use App\Models\ToleranceAmendmentRequest;
use App\Mail\ToleranceAmendmentMailable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;

class VendorController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            if (Auth::user()->role !== 'fabric_vendor') {
                abort(403, 'Unauthorized access.');
            }
            return $next($request);
        });
    }

    public function dashboard()
    {
        $vendorId = Auth::user()->vendor_id;

        $stats = [
            'active_pos' => PurchaseOrder::where('vendor_id', $vendorId)
                ->whereIn('status', ['pending', 'processing'])
                ->count(),
            'pending_items' => PoItem::whereHas('purchaseOrder', function ($q) use ($vendorId) {
                $q->where('vendor_id', $vendorId);
            })->where('status', 'pending')->count(),
            'completed_pos' => PurchaseOrder::where('vendor_id', $vendorId) // Changed from printed_rolls
                ->where('status', 'completed')
                ->count(),
        ];

        $recentOrders = PurchaseOrder::where('vendor_id', $vendorId)
            ->withCount([
                'items', // Total batches
                'items as unique_items_count' => function ($query) {
                    $query->select(DB::raw('count(distinct(item_number))'));
                },
                'items as pending_unique_items_count' => function ($query) {
                    $query->where('status', 'pending')
                          ->select(DB::raw('count(distinct(item_number))'));
                }
            ])
            ->with('items:id,po_id,status') // Eager load for button logic
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return view('vendor.dashboard', compact('stats', 'recentOrders'));
    }

    public function purchaseOrders(Request $request)
    {
        $vendorId = Auth::user()->vendor_id;
        $search = $request->input('search');
        $status = $request->input('status');

        $query = PurchaseOrder::where('vendor_id', $vendorId)
            ->withCount([
                'items', // Total batches
                'items as unique_items_count' => function ($query) {
                    $query->select(DB::raw('count(distinct(item_number))'));
                },
                'items as pending_unique_items_count' => function ($query) {
                    $query->where('status', 'pending')
                          ->select(DB::raw('count(distinct(item_number))'));
                }
            ])
            ->with('items:id,po_id,status'); // Eager load for button logic

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('po_number', 'like', '%' . $search . '%')
                  ->orWhere('reference', 'like', '%' . $search . '%');
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        $perPage = (int) $request->get('per_page', 25);
        if (!in_array($perPage, [10, 25, 50])) {
            $perPage = 25;
        }

        $purchaseOrders = $query->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->appends($request->all());

        return view('vendor.purchase-orders', compact('purchaseOrders', 'search', 'status', 'perPage'));
    }

    public function viewPurchaseOrder($id)
    {
        $vendorId = Auth::user()->vendor_id;

        $purchaseOrder = PurchaseOrder::where('vendor_id', $vendorId)
            ->with([
                'items' => function ($query) {
                    $query->with(['rolls']); // Explicitly load rolls
                }
            ])
            ->findOrFail($id);

        return view('vendor.purchase-order-view', compact('purchaseOrder'));
    }

    // NEW: Show item processing page
    public function processItem($itemId)
    {
        $item = PoItem::with([
            'purchaseOrder',
            'rolls' => function ($query) {
                $query->orderBy('roll_number');
            }
        ])->findOrFail($itemId);

        // Verify vendor access
        if ($item->purchaseOrder->vendor_id != Auth::user()->vendor_id) {
            abort(403);
        }

        return view('vendor.process-item', compact('item'));
    }

    // NEW: Save rolls for an item with delete support
    public function saveItemRolls(Request $request, $itemId)
    {
        $item = PoItem::with(['purchaseOrder', 'rolls'])->findOrFail($itemId);

        // Verify vendor access
        if ($item->purchaseOrder->vendor_id != Auth::user()->vendor_id) {
            abort(403);
        }

        // Manual validation checks
        $validator = \Validator::make($request->all(), [
            'rolls' => 'required|array|min:1',
            'roll_unit' => 'required|in:YD,M,KG',
        ]);

        $validator->after(function ($validator) use ($request) {
            $rolls = $request->input('rolls', []);
            foreach ($rolls as $key => $roll) {
                // Skip validation if the roll is marked for deletion
                if (isset($roll['delete']) && $roll['delete'] == '1') {
                    continue;
                }

                // Validate quantity for non-deleted rolls
                if (!isset($roll['quantity']) || !is_numeric($roll['quantity']) || $roll['quantity'] < 0.01) {
                    $validator->errors()->add("rolls.$key.quantity", "Quantity for roll " . ($key + 1) . " must be a valid number greater than 0.");
                }

                // Validate unit for non-deleted rolls
                if (!isset($roll['unit']) || !in_array($roll['unit'], ['YD', 'M', 'KG'])) {
                    $validator->errors()->add("rolls.$key.unit", "Invalid unit for roll " . ($key + 1));
                }
            }
        });

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            \DB::beginTransaction();

            $rollsData = $request->input('rolls', []);
            $deletedCount = 0;

            // 1. Process Deletions First
            foreach ($rollsData as $index => $rollData) {
                if (isset($rollData['delete']) && $rollData['delete'] == '1' && isset($rollData['id'])) {
                    $roll = Roll::find($rollData['id']);
                    if ($roll && $roll->item_id == $item->id) {
                        if ($roll->qr_code_path && \Storage::exists($roll->qr_code_path)) {
                            \Storage::delete($roll->qr_code_path);
                        }
                        $roll->delete();
                        $deletedCount++;
                    }
                }
            }

            // 2. Fetch Remaining Rolls & New Rolls Data to Re-sequence
            // Get all remaining active rolls from DB, ordered by their *current* sequence
            $existingRolls = $item->rolls()->where('item_id', $item->id)->orderBy('sequence')->orderBy('created_at')->get();

            // Prepare a list of roll data to process (Updating Existing + Creating New)
            // We need to map the inputs to the actual DB records or new entries

            $nextSequence = 1;
            $updatedCount = 0;
            $createdCount = 0;

            // Map input keys to objects for easier lookup
            $inputMap = [];
            foreach ($rollsData as $data) {
                if (isset($data['delete']) && $data['delete'] == '1')
                    continue;
                if (isset($data['id'])) {
                    $inputMap[$data['id']] = $data;
                } else {
                    // New rolls stored in a separate list to append
                    $inputMap['new_' . $nextSequence . '_' . uniqid()] = $data;
                }
            }

            // A. Process Existing Persisted Rolls (Re-sequence them)
            foreach ($existingRolls as $existingRoll) {
                if (isset($inputMap[$existingRoll->id])) {
                    $data = $inputMap[$existingRoll->id];

                    // Determine columns based on unit
                    // User Request: "do it without setting others to 0"

                    $updateData = [
                        'sequence' => $nextSequence,
                        // 'roll_number' will be set below
                        'unit' => $data['unit'],
                        'internal_id' => $data['internal_id'] ?? null,
                    ];

                    // Logic: Map input quantity to the correct column
                    if ($data['unit'] == 'YD') {
                        $updateData['length_yd'] = $data['quantity'];
                    } elseif ($data['unit'] == 'M') {
                        $updateData['length_m'] = $data['quantity'];
                    } else {
                        // KG or others go to weight
                        $updateData['weight'] = $data['quantity'];
                    }

                    // Generate Roll Number: PO-ITEM-SEQ
                    $rollNumber = sprintf(
                        "%s-%s-%03d",
                        $item->purchaseOrder->po_number,
                        $item->item_number,
                        $nextSequence
                    );
                    $updateData['roll_number'] = $rollNumber;

                    $existingRoll->update($updateData);

                    // Update QR Code if name changed (optional, but good practice)
                    // $existingRoll->generateQrCode(); 

                    $nextSequence++;
                    $updatedCount++;
                }
            }

            // B. Process New Rolls (Append to sequence)
            foreach ($rollsData as $data) {
                if ((isset($data['delete']) && $data['delete'] == '1') || isset($data['id'])) {
                    continue; // Skip deleted or already processed existing rolls
                }

                // Prepare create data
                $createData = [
                    'item_id' => $item->id,
                    'sequence' => $nextSequence,
                    'unit' => $data['unit'],
                    'internal_id' => $data['internal_id'] ?? null,
                    'grade' => 'A',
                    'defects' => [],
                    'notes' => '',
                    // Initialize specific measurement columns to 0 (optional)
                    // We REMOVE 'length' generic column as it causes errors
                    'length_yd' => 0,
                    'length_m' => 0,
                    'weight' => 0
                ];

                // Map input quantity to the correct column
                if ($data['unit'] == 'YD') {
                    $createData['length_yd'] = $data['quantity'];
                } elseif ($data['unit'] == 'M') {
                    $createData['length_m'] = $data['quantity'];
                } else {
                    $createData['weight'] = $data['quantity'];
                }

                // Generate Roll Number: PO-ITEM-SEQ
                $rollNumber = sprintf(
                    "%s-%s-%03d",
                    $item->purchaseOrder->po_number,
                    $item->item_number,
                    $nextSequence
                );
                $createData['roll_number'] = $rollNumber;

                $roll = Roll::create($createData);

                try {
                    $roll->generateQrCode();
                } catch (\Exception $e) {
                    \Log::warning('QR generation failed for roll ' . $rollNumber . ': ' . $e->getMessage());
                }

                $nextSequence++;
                $createdCount++;
            }

            // Update item status
            if ($item->status == 'pending' && ($updatedCount + $createdCount) > 0) {
                $item->status = 'processing';
                $item->save();
            }

            // Sync PO status
            $item->purchaseOrder->updateStatusBasedOnItems();

            \DB::commit();

            // Prepare success message
            $message = "Saved successfully. ";
            if ($deletedCount > 0)
                $message .= "$deletedCount deleted. ";
            if ($createdCount > 0)
                $message .= "$createdCount created. ";
            if ($updatedCount > 0)
                $message .= "$updatedCount updated. ";
            $message .= "Rolls re-sequenced.";

            return redirect()->route('vendor.item.process', $itemId)
                ->with('success', $message);

        } catch (\Exception $e) {
            \DB::rollBack();
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    // NEW: Delete individual roll
    public function deleteRoll($rollId)
    {
        $roll = Roll::with('item.purchaseOrder')->findOrFail($rollId);

        // Verify vendor access
        if ($roll->item->purchaseOrder->vendor_id != Auth::user()->vendor_id) {
            abort(403);
        }

        // Delete QR code file if exists
        if ($roll->qr_code_path && \Storage::exists($roll->qr_code_path)) {
            \Storage::delete($roll->qr_code_path);
        }

        $itemId = $roll->item->id;
        $roll->delete();

        return response()->json([
            'success' => true,
            'message' => 'Roll deleted successfully',
            'item_id' => $itemId
        ]);
    }

    public function markItemProcessed($itemId)
    {
        $item = PoItem::findOrFail($itemId);

        if ($item->purchaseOrder->vendor_id != Auth::user()->vendor_id) {
            abort(403);
        }

        // Get PO ID before marking as processed
        $poId = $item->purchaseOrder->id;

        // Mark item as processed
        try {
            $item->markAsProcessed();
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        // Redirect to the purchase order view page
        return redirect()->route('vendor.purchase-order.view', $poId)
            ->with('success', 'Item marked as processed successfully!');
    }

    public function markAsPartialShipment($itemId)
    {
        $item = PoItem::findOrFail($itemId);

        if ($item->purchaseOrder->vendor_id != Auth::user()->vendor_id) {
            abort(403);
        }

        // Check for approval
        if (!$item->hasApprovedPartialShipment()) {
            return redirect()->back()->with('error', 'Partial shipment has not been approved for this item yet.');
        }

        $poId = $item->purchaseOrder->id;

        try {
            $item->splitToPartialShipment();

            // Mark the request as implemented so it cannot be reused
            ToleranceAmendmentRequest::where('po_item_id', $item->id)
                ->where('type', 'partial_shipment')
                ->where('status', 'approved')
                ->update(['status' => 'implemented', 'actioned_at' => now()]);

        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('vendor.purchase-order.view', $poId)
            ->with('success', 'Partial shipment processed! A follow-up batch has been created for the remaining balance.');
    }

    public function requestPartialShipment(Request $request)
    {
        $request->validate([
            'po_item_id' => 'required|exists:po_items,id',
            'requested_qty' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:1000',
        ]);

        $item = PoItem::findOrFail($request->po_item_id);

        if ($item->purchaseOrder->vendor_id != Auth::user()->vendor_id) {
            abort(403);
        }

        // Check for existing pending request
        $existing = ToleranceAmendmentRequest::where('po_item_id', $item->id)
            ->where('type', 'partial_shipment')
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            return redirect()->back()->with('error', 'There is already a pending partial shipment request for this item.');
        }

        $amendmentRequest = ToleranceAmendmentRequest::create([
            'po_item_id' => $item->id,
            'type' => 'partial_shipment',
            'requested_qty' => $request->requested_qty,
            'reason' => $request->reason,
            'status' => 'pending'
        ]);

        // Trigger Notifications for Admins
        try {
            $admins = \App\Models\User::whereIn('role', ['admin', 'fabric_admin'])->get();
            \Illuminate\Support\Facades\Notification::send($admins, new \App\Notifications\ToleranceRequestNotification($amendmentRequest));
        } catch (\Exception $e) {
            \Log::error("Failed to send partial shipment notification: " . $e->getMessage());
        }

        $approverBadge = Setting::getValue('Approval');
        
        // Use custom approver lookup logic from ToleranceAmendment
        if ($approverBadge) {
            $approverData = \DB::connection('people_function')
                ->table('employees')
                ->where('badge', $approverBadge)
                ->first();

            if ($approverData && !empty($approverData->email)) {
                try {
                    \Mail::to($approverData->email)
                        ->send(new \App\Mail\ToleranceAmendmentMailable($amendmentRequest));
                } catch (\Exception $e) {
                    \Log::error("Failed to send partial shipment email: " . $e->getMessage());
                }
            } else {
                \Log::warning("Approver badge found but no email: " . $approverBadge);
                // Fallback attempt
                $this->sendFallbackNotification($amendmentRequest);
            }
        } else {
            \Log::warning("No Approver badge found in settings");
            $this->sendFallbackNotification($amendmentRequest);
        }

        return redirect()->back()->with('success', 'Partial shipment request sent successfully! You will be notified once approved.');
    }

    private function sendFallbackNotification($amendmentRequest)
    {
        try {
            \Mail::to(\App\Models\Setting::getValue('admin_notification_email', 'admin@example.com'))
                ->send(new \App\Mail\ToleranceAmendmentMailable($amendmentRequest));
        } catch (\Exception $e) {
            \Log::error("Failed to send fallback partial shipment email: " . $e->getMessage());
        }
    }

    // NEW: Upload rolls data via Excel/PDF
    public function uploadRollsData(Request $request, $itemId)
    {
        $item = PoItem::with(['purchaseOrder'])->findOrFail($itemId);

        if ($item->purchaseOrder->vendor_id != Auth::user()->vendor_id) {
            abort(403);
        }

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,pdf|max:2048',
        ]);

        $file = $request->file('file');
        $extension = $file->getClientOriginalExtension();
        $rollsData = [];

        try {
            if ($extension === 'pdf') {
                $parser = new PdfParser();
                $pdf = $parser->parseFile($file->getPathname());
                $text = $pdf->getText();
                
                // Simple AI-like heuristic: look for patterns that look like [ID] [Quantity]
                // For now, look for lines with a number at the end
                $lines = explode("\n", $text);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (preg_match('/([A-Z0-9_-]+)?\s*(\d+(?:\.\d+)?)$/', $line, $matches)) {
                        $rollsData[] = [
                            'internal_id' => $matches[1] ?? '',
                            'quantity' => (float) $matches[2]
                        ];
                    }
                }
            } else {
                // Excel/CSV
                $data = Excel::toArray([], $file);
                if (!empty($data) && !empty($data[0])) {
                    foreach ($data[0] as $row) {
                        // Skip header or empty rows
                        if (!isset($row[1]) || !is_numeric($row[1])) continue;
                        
                        $rollsData[] = [
                            'internal_id' => $row[0] ?? '',
                            'quantity' => (float) $row[1]
                        ];
                    }
                }
            }

            if (empty($rollsData)) {
                return response()->json(['success' => false, 'message' => 'No valid data found in the file.']);
            }

            return response()->json([
                'success' => true,
                'data' => $rollsData,
                'message' => count($rollsData) . ' rolls identified successfully.'
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error parsing file: ' . $e->getMessage()]);
        }
    }

    // NEW: Revert item from completed back to processing
    public function revertProcessing($itemId)
    {
        $item = PoItem::findOrFail($itemId);

        if ($item->purchaseOrder->vendor_id != Auth::user()->vendor_id) {
            abort(403);
        }

        $item->status = 'processing';
        $item->save();

        // Sync PO status
        $item->purchaseOrder->updateStatusBasedOnItems();

        return redirect()->back()->with('success', 'Item reverted to processing status!');
    }

    public function requestToleranceAmendment(Request $request)
    {
        $request->validate([
            'po_item_id' => 'required|exists:po_items,id',
            'new_underdelivery' => 'required|numeric|min:0|max:100',
            'new_overdelivery' => 'required|numeric|min:0|max:100',
            'reason' => 'required|string|min:10'
        ]);

        $item = PoItem::findOrFail($request->po_item_id);

        if ($item->purchaseOrder->vendor_id != Auth::user()->vendor_id) {
            abort(403);
        }

        $approverBadge = Setting::getValue('Approval');
        
        if (!$approverBadge) {
            return redirect()->back()->with('error', 'Approver badge is not configured in settings.');
        }

        // Get approver email from external database
        $approver = DB::connection('people_function')
            ->table('employees')
            ->where('badge', $approverBadge)
            ->first();

        if (!$approver) {
            return redirect()->back()->with('error', 'Approver with badge ' . $approverBadge . ' not found in employees database.');
        }

        if (empty($approver->email)) {
            return redirect()->back()->with('error', 'Approver found but email is missing in employees database.');
        }

        $amendmentRequest = ToleranceAmendmentRequest::create([
            'po_item_id' => $item->id,
            'old_underdelivery' => $item->getEffectiveUnderdelivery(),
            'old_overdelivery' => $item->getEffectiveOverdelivery(),
            'new_underdelivery' => $request->new_underdelivery,
            'new_overdelivery' => $request->new_overdelivery,
            'reason' => $request->reason,
            'approver_badge' => $approverBadge,
            'status' => 'pending'
        ]);

        // Trigger Notifications for Admins
        try {
            $admins = \App\Models\User::whereIn('role', ['admin', 'fabric_admin'])->get();
            \Illuminate\Support\Facades\Notification::send($admins, new \App\Notifications\ToleranceRequestNotification($amendmentRequest));
        } catch (\Exception $e) {
            \Log::error("Failed to send tolerance amendment notification to admins: " . $e->getMessage());
        }

        try {
            \Mail::to($approver->email)->send(new \App\Mail\ToleranceAmendmentMailable($amendmentRequest));
        } catch (\Exception $e) {
            return redirect()->back()->with('success', 'Amendment requested, but mail could not be sent. Check logs for details.')->with('warning', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Amendment request has been sent for approval.');
    }

    public function generatePackingSlip(Request $request, $poId)
    {
        $vendorId = Auth::user()->vendor_id;

        $purchaseOrder = PurchaseOrder::where('vendor_id', $vendorId)
            ->with(['items.rolls'])
            ->findOrFail($poId);

        $request->validate([
            'items' => 'required|array',
            'items.*' => 'exists:po_items,id',
            'delivery_note' => 'required|string|max:255'
        ]);

        // Create packing slip
        $packingSlip = PackingSlip::create([
            'po_id' => $poId,
            'vendor_id' => $vendorId,
            'items' => $request->items,
            'delivery_note' => $request->delivery_note
        ]);

        // Mark rolls as printed
        Roll::whereIn('item_id', $request->items)->update([
            'is_printed' => true,
            'printed_at' => now()
        ]);

        return redirect()->route('vendor.packing-slip.view', $packingSlip->id);
    }

    public function viewPackingSlip($id)
    {
        $vendorId = Auth::user()->vendor_id;

        $packingSlip = PackingSlip::where('vendor_id', $vendorId)
            ->with(['purchaseOrder.vendor'])
            ->findOrFail($id);

        // Fetch selected items with their rolls
        $selectedItems = PoItem::whereIn('id', $packingSlip->items)
            ->with([
                'rolls' => function ($query) {
                    $query->orderBy('sequence');
                }
            ])
            ->get();

        // Redirect to print view (PDF)
        return redirect()->route('vendor.packing-slip.print', $packingSlip->id);
    }

    public function printPackingSlip($id)
    {
        $vendorId = Auth::user()->vendor_id;

        $packingSlip = PackingSlip::where('vendor_id', $vendorId)
            ->with(['purchaseOrder.vendor'])
            ->findOrFail($id);

        // Fetch selected items with their rolls
        $selectedItems = PoItem::whereIn('id', $packingSlip->items)
            ->orderBy('item_number', 'asc')
            ->with([
                'rolls' => function ($query) {
                    $query->orderBy('sequence');
                }
            ])
            ->get();

        // Generate PDF
        $pdf = Pdf::loadView('vendor.pdf.packing-slip', compact('packingSlip', 'selectedItems'));

        // Update printed count
        $packingSlip->markPrinted();

        return $pdf->stream('packing-slip-' . $packingSlip->slip_number . '.pdf');
    }

    // NEW: Quick generate packing slip for all completed items in a PO
    public function quickGeneratePackingSlip(Request $request, $poId)
    {
        $vendorId = Auth::user()->vendor_id;

        // Verify PO ownership
        $purchaseOrder = PurchaseOrder::where('vendor_id', $vendorId)->findOrFail($poId);

        $request->validate([
            'delivery_note' => 'required|string|max:255'
        ]);

        // Find all completed items
        $completedItemIds = PoItem::where('po_id', $poId)
            ->where('status', 'completed')
            ->pluck('id')
            ->toArray();

        if (empty($completedItemIds)) {
            return redirect()->back()->with('error', 'No completed items found to generate packing slip.');
        }

        // Create new packing slip
        $packingSlip = PackingSlip::create([
            'po_id' => $poId,
            'vendor_id' => $vendorId,
            'items' => $completedItemIds,
            'delivery_note' => $request->delivery_note
        ]);

        // Mark rolls as printed
        Roll::whereIn('item_id', $completedItemIds)->update([
            'is_printed' => true,
            'printed_at' => now()
        ]);

        // Redirect to print view
        return redirect()->route('vendor.packing-slip.print', $packingSlip->id);
    }
}