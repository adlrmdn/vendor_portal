<?php

namespace App\Http\Controllers;

use App\Models\PackingSlip;
use App\Models\PoItem;
use App\Models\PurchaseOrder;
use App\Models\Roll;
use App\Models\Setting;
use App\Models\ToleranceAmendmentRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Smalot\PdfParser\Parser as PdfParser;

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
            'processing_items' => PoItem::whereHas('purchaseOrder', function ($q) use ($vendorId) {
                $q->where('vendor_id', $vendorId);
            })->where('status', 'processing')->count(),
            'completed_pos' => PurchaseOrder::where('vendor_id', $vendorId) // Changed from printed_rolls
                ->where('status', 'completed')
                ->count(),
        ];

        $recentOrders = PurchaseOrder::where('vendor_id', $vendorId)
            ->withCount([
                'items', // Total batches
                // "Items" = real product lines. Exclude partial-shipment shadows
                // (splitToPartialShipment suffixes the batch with -P2/-P3), so two
                // styles sharing one D365 item_number count as two items, while an
                // item split across shipments still counts as one.
                'items as unique_items_count' => function ($query) {
                    $query->where('batch', 'not like', '%-P%');
                },
                'items as pending_unique_items_count' => function ($query) {
                    $query->where('status', 'pending')
                        ->where('batch', 'not like', '%-P%');
                },
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
                // "Items" = real product lines. Exclude partial-shipment shadows
                // (splitToPartialShipment suffixes the batch with -P2/-P3), so two
                // styles sharing one D365 item_number count as two items, while an
                // item split across shipments still counts as one.
                'items as unique_items_count' => function ($query) {
                    $query->where('batch', 'not like', '%-P%');
                },
                'items as pending_unique_items_count' => function ($query) {
                    $query->where('status', 'pending')
                        ->where('batch', 'not like', '%-P%');
                },
            ])
            ->with('items:id,po_id,status'); // Eager load for button logic

        if ($search) {
            // Smart style-name search: split the query into words and require an
            // item whose style name (po_items.batch) contains every word, any order.
            $styleTerms = preg_split('/\s+/', trim($search), -1, PREG_SPLIT_NO_EMPTY);

            $query->where(function ($q) use ($search, $styleTerms) {
                $q->where('po_number', 'like', '%'.$search.'%')
                    // PC (Preliminary Contract) reference, case-insensitive
                    ->orWhereRaw('LOWER(reference) LIKE ?', ['%'.mb_strtolower($search).'%'])
                    ->orWhereHas('items', function ($iq) use ($search) {
                        $iq->where('plm_number', 'like', '%'.$search.'%');
                    })
                    ->orWhereHas('items', function ($iq) use ($styleTerms) {
                        // Style name lives in po_items.batch (e.g. "MOC Eagle Blue - FALL-26").
                        // Case-insensitive, every word must match; portable across pgsql/sqlite.
                        foreach ($styleTerms as $term) {
                            $iq->whereRaw('LOWER(batch) LIKE ?', ['%'.mb_strtolower($term).'%']);
                        }
                    });
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        $perPage = (int) $request->get('per_page', 25);
        if (! in_array($perPage, [10, 25, 50])) {
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
                },
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
            },
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

        // All rolls carry the item's original order metric as their unit. Every
        // metric column can be filled, but only the order metric is mandatory.
        // Non-metric order units (PCS/UNIT) keep their qty in the weight column.
        $orderUnit = strtoupper($item->unit);
        $primaryField = match ($orderUnit) {
            'YD' => 'length_yd',
            'M' => 'length_m',
            default => 'weight',
        };

        // Manual validation checks
        $validator = \Validator::make($request->all(), [
            'rolls' => 'required|array|min:1',
        ]);

        $validator->after(function ($validator) use ($request, $orderUnit, $primaryField) {
            $rowNo = 0;
            foreach ($request->input('rolls', []) as $key => $roll) {
                // Skip validation if the roll is marked for deletion. Deleted
                // rows must not consume a row number — the UI's "Roll N"
                // labels (updateRollNumbers() in process-item.blade.php) only
                // count non-deleted rows, and this counter has to match that
                // exactly or the error message points at the wrong card.
                if (isset($roll['delete']) && $roll['delete'] == '1') {
                    continue;
                }
                $rowNo++;

                // The order-metric quantity is the only mandatory figure
                $primary = $roll[$primaryField] ?? null;
                if ((! is_numeric($primary) || $primary < 0.01) && in_array($orderUnit, ['YD', 'M'])) {
                    // YD and M are interchangeable — the other length satisfies the
                    // requirement; the missing one is derived at save time.
                    $sibling = $roll[$primaryField === 'length_yd' ? 'length_m' : 'length_yd'] ?? null;
                    if (is_numeric($sibling) && $sibling >= 0.01) {
                        $primary = $sibling;
                    }
                }
                if (! is_numeric($primary) || $primary < 0.01) {
                    $validator->errors()->add("rolls.$key.$primaryField", "Roll $rowNo: the $orderUnit quantity must be a valid number greater than 0.");
                }

                // Optional metrics must still be sane numbers when provided
                foreach (['length_yd' => 'YD', 'length_m' => 'M', 'weight' => 'KG'] as $field => $label) {
                    if ($field !== $primaryField && isset($roll[$field]) && $roll[$field] !== '' && (! is_numeric($roll[$field]) || $roll[$field] < 0)) {
                        $validator->errors()->add("rolls.$key.$field", "Roll $rowNo: the $label value must be a valid non-negative number.");
                    }
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

            $nextSequence = Roll::siblingSequenceOffset($item) + 1;
            $updatedCount = 0;
            $createdCount = 0;

            // Map input keys to objects for easier lookup
            $inputMap = [];
            foreach ($rollsData as $data) {
                if (isset($data['delete']) && $data['delete'] == '1') {
                    continue;
                }
                if (isset($data['id'])) {
                    $inputMap[$data['id']] = $data;
                } else {
                    // New rolls stored in a separate list to append
                    $inputMap['new_'.$nextSequence.'_'.uniqid()] = $data;
                }
            }

            // Shared metric mapping: every metric column is stored as entered; the
            // missing one of YD/M is derived from the other (they are interchangeable).
            $metricData = function (array $data) use ($orderUnit) {
                $toFloat = fn ($v) => (isset($v) && $v !== '') ? (float) $v : null;

                $yd = $toFloat($data['length_yd'] ?? null);
                $m = $toFloat($data['length_m'] ?? null);
                $kg = $toFloat($data['weight'] ?? null);

                if ($yd !== null && $m === null) {
                    $m = round($yd * 0.9144, 2);
                } elseif ($m !== null && $yd === null) {
                    $yd = round($m / 0.9144, 2);
                }

                return [
                    'unit' => $orderUnit,
                    'length_yd' => $yd,
                    'length_m' => $m,
                    'weight' => $kg,
                    'internal_id' => ($data['internal_id'] ?? '') !== '' ? $data['internal_id'] : null,
                    'vendor_roll_no' => ($data['vendor_roll_no'] ?? '') !== '' ? $data['vendor_roll_no'] : null,
                    'bale_no' => ($data['bale_no'] ?? '') !== '' ? $data['bale_no'] : null,
                    'color' => ($data['color'] ?? '') !== '' ? $data['color'] : null,
                ];
            };

            // A. Process Existing Persisted Rolls (Re-sequence them)
            foreach ($existingRolls as $existingRoll) {
                if (isset($inputMap[$existingRoll->id])) {
                    $data = $inputMap[$existingRoll->id];

                    $updateData = $metricData($data) + [
                        'sequence' => $nextSequence,
                    ];

                    // Generate Roll Number: PO-ITEM-SEQ
                    $rollNumber = Roll::buildRollNumber(
                        $item->purchaseOrder->po_number,
                        $item->item_number,
                        $nextSequence
                    );
                    $updateData['roll_number'] = $rollNumber;

                    $existingRoll->update($updateData);

                    $nextSequence++;
                    $updatedCount++;
                }
            }

            // B. Process New Rolls (Append to sequence)
            foreach ($rollsData as $data) {
                if ((isset($data['delete']) && $data['delete'] == '1') || isset($data['id'])) {
                    continue; // Skip deleted or already processed existing rolls
                }

                $createData = $metricData($data) + [
                    'item_id' => $item->id,
                    'sequence' => $nextSequence,
                    'grade' => 'A',
                    'defects' => [],
                    'notes' => '',
                ];

                // Generate Roll Number: PO-ITEM-SEQ
                $rollNumber = Roll::buildRollNumber(
                    $item->purchaseOrder->po_number,
                    $item->item_number,
                    $nextSequence
                );
                $createData['roll_number'] = $rollNumber;

                Roll::create($createData);

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
            $message = 'Saved successfully. ';
            if ($deletedCount > 0) {
                $message .= "$deletedCount deleted. ";
            }
            if ($createdCount > 0) {
                $message .= "$createdCount created. ";
            }
            if ($updatedCount > 0) {
                $message .= "$updatedCount updated. ";
            }
            $message .= 'Rolls re-sequenced.';

            return redirect()->route('vendor.item.process', $itemId)
                ->with('success', $message);

        } catch (\Exception $e) {
            \DB::rollBack();

            return redirect()->back()->with('error', 'Error: '.$e->getMessage());
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

        $itemId = $roll->item->id;
        $roll->delete();

        return response()->json([
            'success' => true,
            'message' => 'Roll deleted successfully',
            'item_id' => $itemId,
        ]);
    }

    public function rollQrCode($rollId)
    {
        $roll = Roll::with('item.purchaseOrder')->findOrFail($rollId);

        if ($roll->item->purchaseOrder->vendor_id != Auth::user()->vendor_id) {
            abort(403);
        }

        return view('rolls.qr-view', [
            'roll' => $roll,
            'qrSvg' => \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(260)->generate($roll->qrPayload()),
            'qtyCaption' => $roll->qtyCaption(),
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
        if (! $item->hasApprovedPartialShipment()) {
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
            'status' => 'pending',
        ]);

        $approverEmails = $this->fabricApproverEmails();

        if (! empty($approverEmails)) {
            try {
                \Mail::to($approverEmails)->send(new \App\Mail\ToleranceAmendmentMailable($amendmentRequest));
            } catch (\Exception $e) {
                \Log::error('Failed to send partial shipment email: '.$e->getMessage());
            }
        } else {
            \Log::warning('No fabric approver email configured (Admin > Workflow)');
            $this->sendFallbackNotification($amendmentRequest);
        }

        return redirect()->back()->with('success', 'Partial shipment request sent successfully! You will be notified once approved.');
    }

    /**
     * Approver(s) for fabric tolerance/partial-shipment requests, configured
     * on the admin Workflow page (Setting `fabric_approver_email`). Replaces
     * the old badge -> people_function lookup, which silently produced no
     * recipient whenever the badge wasn't a valid employees.badge value.
     *
     * @return array<int, string>
     */
    private function fabricApproverEmails(): array
    {
        $raw = Setting::getValue('fabric_approver_email', 'leon@megaperintis.co.id');
        $emails = [];
        foreach (preg_split('/[,;]+/', (string) $raw) as $email) {
            $email = trim($email);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $email;
            }
        }

        return $emails;
    }

    private function sendFallbackNotification($amendmentRequest)
    {
        try {
            \Mail::to(\App\Models\Setting::getValue('admin_notification_email', 'admin@example.com'))
                ->send(new \App\Mail\ToleranceAmendmentMailable($amendmentRequest));
        } catch (\Exception $e) {
            \Log::error('Failed to send fallback partial shipment email: '.$e->getMessage());
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

        try {
            if ($extension === 'pdf') {
                $parser = new PdfParser;
                $text = $parser->parseFile($file->getPathname())->getText();
                $rollsData = \App\Services\RollsImportService::parsePdfText($text);
            } else {
                // Excel/CSV — template header mapped by name, legacy [lot, qty] otherwise
                $rows = Excel::toArray([], $file)[0] ?? [];
                $rollsData = \App\Services\RollsImportService::parseRows($rows);
            }

            if (empty($rollsData)) {
                return response()->json(['success' => false, 'message' => 'No valid data found in the file.']);
            }

            return response()->json([
                'success' => true,
                'data' => $rollsData,
                'message' => count($rollsData).' rolls identified successfully.',
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error parsing file: '.$e->getMessage()]);
        }
    }

    // Download the uniform Excel template for the rolls import.
    public function downloadRollsTemplate($itemId)
    {
        $item = PoItem::with('purchaseOrder')->findOrFail($itemId);

        if ($item->purchaseOrder->vendor_id != Auth::user()->vendor_id) {
            abort(403);
        }

        $rows = [\App\Services\RollsImportService::templateHeader($item)];
        $export = new class($rows) implements \Maatwebsite\Excel\Concerns\FromArray
        {
            public function __construct(private array $rows) {}

            public function array(): array
            {
                return $this->rows;
            }
        };

        $name = 'rolls-template-'.preg_replace('/[^A-Za-z0-9_-]+/', '-', $item->purchaseOrder->po_number.'-'.$item->item_number).'.xlsx';

        return Excel::download($export, $name);
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
            'reason' => 'required|string|min:10',
        ]);

        $item = PoItem::findOrFail($request->po_item_id);

        if ($item->purchaseOrder->vendor_id != Auth::user()->vendor_id) {
            abort(403);
        }

        // Only one pending tolerance amendment per item at a time.
        $existing = ToleranceAmendmentRequest::where('po_item_id', $item->id)
            ->where('type', 'tolerance')
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            return redirect()->back()->with('error', 'There is already a pending tolerance amendment request for this item.');
        }

        $approverEmails = $this->fabricApproverEmails();

        if (empty($approverEmails)) {
            return redirect()->back()->with('error', 'Fabric approver email is not configured. Set it on the admin Workflow page.');
        }

        $amendmentRequest = ToleranceAmendmentRequest::create([
            'po_item_id' => $item->id,
            'old_underdelivery' => $item->getEffectiveUnderdelivery(),
            'old_overdelivery' => $item->getEffectiveOverdelivery(),
            'new_underdelivery' => $request->new_underdelivery,
            'new_overdelivery' => $request->new_overdelivery,
            'reason' => $request->reason,
            'status' => 'pending',
        ]);

        try {
            \Mail::to($approverEmails)->send(new \App\Mail\ToleranceAmendmentMailable($amendmentRequest));
        } catch (\Exception $e) {
            return redirect()->back()->with('success', 'Amendment requested, but mail could not be sent. Check logs for details.')->with('warning', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Amendment request has been sent for approval.');
    }

    /**
     * Recall a pending tolerance/partial-shipment request. Lets a vendor
     * invalidate a stale ask (e.g. wrong numbers, changed mind) so the "only
     * one pending request per item" guardrail doesn't lock them out of
     * submitting the correct one. Any signed approval-email link for a
     * cancelled request is already rejected by ApprovalController, since it
     * only acts on status === 'pending'.
     */
    public function cancelAmendmentRequest($requestId)
    {
        $amendmentRequest = ToleranceAmendmentRequest::with('poItem.purchaseOrder')->findOrFail($requestId);

        if ($amendmentRequest->poItem->purchaseOrder->vendor_id != Auth::user()->vendor_id) {
            abort(403);
        }

        if ($amendmentRequest->status !== 'pending') {
            return redirect()->back()->with('error', 'Only a pending request can be cancelled.');
        }

        $amendmentRequest->update([
            'status' => 'cancelled',
            'actioned_at' => now(),
        ]);

        $label = $amendmentRequest->type === 'partial_shipment' ? 'Partial shipment' : 'Tolerance amendment';

        return redirect()->back()->with('success', $label.' request cancelled. You can now submit a new one.');
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
            'delivery_note' => 'required|string|max:255',
        ]);

        // Create packing slip
        $packingSlip = PackingSlip::create([
            'po_id' => $poId,
            'vendor_id' => $vendorId,
            'items' => $request->items,
            'delivery_note' => $request->delivery_note,
        ]);

        // Mark rolls as printed
        Roll::whereIn('item_id', $request->items)->update([
            'is_printed' => true,
            'printed_at' => now(),
        ]);

        return redirect()->route('vendor.packing-slip.view', [
            'id' => $packingSlip->id,
            'show_secondary' => $request->input('show_secondary', 0),
            'reverse_units' => $request->input('reverse_units', 0),
        ]);
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
                },
            ])
            ->get();

        // Redirect to print view (PDF)
        return redirect()->route('vendor.packing-slip.print', [
            'id' => $packingSlip->id,
            'show_secondary' => request()->query('show_secondary', 0),
            'reverse_units' => request()->query('reverse_units', 0),
        ]);
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
                },
            ])
            ->get();

        $showSecondary = request()->query('show_secondary', 0);
        $reverseUnits = request()->query('reverse_units', 0);

        // Resolve view template dynamically (improved template fetching logic)
        $view = $this->resolvePackingSlipView();

        // Generate PDF
        $pdf = Pdf::loadView($view, compact('packingSlip', 'selectedItems', 'showSecondary', 'reverseUnits'));

        // Update printed count
        $packingSlip->markPrinted();

        return $pdf->stream('packing-slip-'.$packingSlip->slip_number.'.pdf');
    }

    protected function resolvePackingSlipView()
    {
        foreach (['pdf.packing-slip', 'admin.pdf.packing-slip', 'vendor.pdf.packing-slip'] as $view) {
            if (view()->exists($view)) {
                return $view;
            }
        }

        return 'vendor.pdf.packing-slip';
    }

    // NEW: Quick generate packing slip for all completed items in a PO
    public function quickGeneratePackingSlip(Request $request, $poId)
    {
        $vendorId = Auth::user()->vendor_id;

        // Verify PO ownership
        $purchaseOrder = PurchaseOrder::where('vendor_id', $vendorId)->findOrFail($poId);

        $request->validate([
            'delivery_note' => 'required|string|max:255',
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
            'delivery_note' => $request->delivery_note,
        ]);

        // Mark rolls as printed
        Roll::whereIn('item_id', $completedItemIds)->update([
            'is_printed' => true,
            'printed_at' => now(),
        ]);

        // Redirect to print view
        return redirect()->route('vendor.packing-slip.print', [
            'id' => $packingSlip->id,
            'show_secondary' => $request->input('show_secondary', 0),
            'reverse_units' => $request->input('reverse_units', 0),
        ]);
    }
}
