<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth; // Add this import
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');

        $this->middleware(function ($request, $next) {
            if (!in_array(Auth::user()->role, ['admin', 'fabric_admin'])) {
                abort(403, 'Unauthorized access.');
            }
            return $next($request);
        });
    }

    public function dashboard()
    {
        $stats = [
            'total_pos' => PurchaseOrder::count(),
            'active_pos' => PurchaseOrder::whereIn('status', ['pending', 'processing'])->count(),
            'completed_pos' => PurchaseOrder::where('status', 'completed')->count(),
            'total_vendors' => Vendor::where('is_active', true)->count(),
        ];

        $recentOrders = PurchaseOrder::with(['vendor'])
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
            ->with('items:id,po_id,status')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return view('admin.dashboard', compact('stats', 'recentOrders'));
    }

    public function purchaseOrders(Request $request)
    {
        $search = $request->input('search');
        $status = $request->input('status');
        $vendorId = $request->input('vendor_id');

        $query = PurchaseOrder::with(['vendor'])
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

        if ($vendorId) {
            $query->where('vendor_id', $vendorId);
        }

        $perPage = (int) $request->get('per_page', 25);
        if (!in_array($perPage, [10, 25, 50])) {
            $perPage = 25;
        }

        $purchaseOrders = $query->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->appends($request->all());
        $vendors = Vendor::where('is_active', true)->orderBy('name')->get();

        return view('admin.purchase-orders', compact('purchaseOrders', 'vendors', 'search', 'status', 'vendorId', 'perPage'));
    }

    public function viewPurchaseOrder($id)
    {
        $purchaseOrder = PurchaseOrder::with([
            'vendor',
            'items' => function ($query) {
                $query->with(['rolls']);
            }
        ])->findOrFail($id);

        return view('admin.purchase-order-view', compact('purchaseOrder'));
    }

    public function vendors()
    {
        $vendors = Vendor::withCount(['purchaseOrders', 'activePurchaseOrders'])
            ->orderBy('name')
            ->paginate(20);

        return view('admin.vendors', compact('vendors'));
    }

    public function vendorDetail($id)
    {
        $vendor = Vendor::with([
            'purchaseOrders' => function ($query) {
                $query->orderBy('created_at', 'desc');
            }
        ])->findOrFail($id);

        return view('admin.vendor-detail', compact('vendor'));
    }

    public function updatePurchaseOrder(Request $request, $id)
    {
        $purchaseOrder = PurchaseOrder::findOrFail($id);

        $request->validate([
            'status' => 'required|in:pending,processing,completed,cancelled',
            'delivery_date' => 'nullable|date',
        ]);

        $purchaseOrder->update([
            'status' => $request->status,
            'delivery_date' => $request->delivery_date,
        ]);

        return redirect()->back()->with('success', 'Purchase order updated successfully');
    }

    public function storeVendor(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'vendor_code' => 'required|string|max:50|unique:vendors,vendor_code',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $vendor = \App\Models\Vendor::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'name' => $request->name,
            'vendor_code' => $request->vendor_code,
            'is_active' => $request->has('is_active'),
            'contact_info' => [
                'contact_person' => $request->contact_person,
                'email' => $request->email,
                'phone' => $request->phone,
                'address' => $request->address,
            ],
        ]);

        return redirect()->back()->with('success', 'Vendor created successfully');
    }

    public function updateVendor(Request $request, $id)
    {
        $vendor = Vendor::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'vendor_code' => 'required|string|max:50|unique:vendors,vendor_code,' . $id,
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $contactInfo = $vendor->contact_info ?? [];
        $contactInfo['contact_person'] = $request->contact_person;
        $contactInfo['email'] = $request->email;
        $contactInfo['phone'] = $request->phone;
        $contactInfo['address'] = $request->address;

        $vendor->update([
            'name' => $request->name,
            'vendor_code' => $request->vendor_code,
            'is_active' => $request->has('is_active'),
            'contact_info' => $contactInfo,
        ]);

        return redirect()->back()->with('success', 'Vendor updated successfully');
    }

    // --- Admin Item Processing Functionalities (Mirrors Vendor with Admin Privileges) ---

    public function processItem($itemId)
    {
        $item = \App\Models\PoItem::with([
            'purchaseOrder',
            'rolls' => function ($query) {
                $query->orderBy('roll_number');
            }
        ])->findOrFail($itemId);

        // Admin can access any item
        return view('admin.process-item', compact('item'));
    }

    public function saveItemRolls(Request $request, $itemId)
    {
        $item = \App\Models\PoItem::with(['purchaseOrder', 'rolls'])->findOrFail($itemId);

        // Validation (Same as Vendor)
        $validator = \Validator::make($request->all(), [
            'rolls' => 'required|array|min:1',
            'roll_unit' => 'required|in:YD,M,KG',
        ]);

        $validator->after(function ($validator) use ($request) {
            $rolls = $request->input('rolls', []);
            foreach ($rolls as $key => $roll) {
                if (isset($roll['delete']) && $roll['delete'] == '1')
                    continue;
                if (!isset($roll['quantity']) || !is_numeric($roll['quantity']) || $roll['quantity'] < 0.01) {
                    $validator->errors()->add("rolls.$key.quantity", "Quantity for roll " . ($key + 1) . " must be valid.");
                }
                if (!isset($roll['unit']) || !in_array($roll['unit'], ['YD', 'M', 'KG'])) {
                    $validator->errors()->add("rolls.$key.unit", "Invalid unit for roll " . ($key + 1));
                }
            }
        });

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            DB::beginTransaction();

            $rollsData = $request->input('rolls', []);
            $deletedCount = 0;

            // 1. Process Deletions
            foreach ($rollsData as $index => $rollData) {
                if (isset($rollData['delete']) && $rollData['delete'] == '1' && isset($rollData['id'])) {
                    $roll = \App\Models\Roll::find($rollData['id']);
                    if ($roll && $roll->item_id == $item->id) {
                        if ($roll->qr_code_path && \Storage::exists($roll->qr_code_path)) {
                            \Storage::delete($roll->qr_code_path);
                        }
                        $roll->delete();
                        $deletedCount++;
                    }
                }
            }

            // 2. Process Updates and Creates (Re-sequencing)
            $existingRolls = $item->rolls()->where('item_id', $item->id)->orderBy('sequence')->orderBy('created_at')->get();
            $nextSequence = 1;
            $updatedCount = 0;
            $createdCount = 0;

            $inputMap = [];
            foreach ($rollsData as $data) {
                if (isset($data['delete']) && $data['delete'] == '1')
                    continue;
                if (isset($data['id'])) {
                    $inputMap[$data['id']] = $data;
                } else {
                    $inputMap['new_' . $nextSequence . '_' . uniqid()] = $data;
                }
            }

            // A. Update Existing
            foreach ($existingRolls as $existingRoll) {
                if (isset($inputMap[$existingRoll->id])) {
                    $data = $inputMap[$existingRoll->id];

                    $updateData = [
                        'sequence' => $nextSequence,
                        'unit' => $data['unit'],
                        'internal_id' => $data['internal_id'] ?? null,
                    ];

                    if ($data['unit'] == 'YD')
                        $updateData['length_yd'] = $data['quantity'];
                    elseif ($data['unit'] == 'M')
                        $updateData['length_m'] = $data['quantity'];
                    else
                        $updateData['weight'] = $data['quantity'];

                    $rollNumber = sprintf("%s-%s-%03d", $item->purchaseOrder->po_number, $item->item_number, $nextSequence);
                    $updateData['roll_number'] = $rollNumber;

                    $existingRoll->update($updateData);
                    $nextSequence++;
                    $updatedCount++;
                }
            }

            // B. Create New
            foreach ($rollsData as $data) {
                if ((isset($data['delete']) && $data['delete'] == '1') || isset($data['id']))
                    continue;

                $createData = [
                    'item_id' => $item->id,
                    'sequence' => $nextSequence,
                    'unit' => $data['unit'],
                    'internal_id' => $data['internal_id'] ?? null,
                    'grade' => 'A',
                    'defects' => [],
                    'notes' => '',
                    'length_yd' => 0,
                    'length_m' => 0,
                    'weight' => 0
                ];

                if ($data['unit'] == 'YD')
                    $createData['length_yd'] = $data['quantity'];
                elseif ($data['unit'] == 'M')
                    $createData['length_m'] = $data['quantity'];
                else
                    $createData['weight'] = $data['quantity'];

                $rollNumber = sprintf("%s-%s-%03d", $item->purchaseOrder->po_number, $item->item_number, $nextSequence);
                $createData['roll_number'] = $rollNumber;

                $roll = \App\Models\Roll::create($createData);
                try {
                    $roll->generateQrCode();
                } catch (\Exception $e) {
                }

                $nextSequence++;
                $createdCount++;
            }

            if ($item->status == 'pending' && ($updatedCount + $createdCount) > 0) {
                $item->status = 'processing';
                $item->save();
            }

            $item->purchaseOrder->updateStatusBasedOnItems();
            DB::commit();

            return redirect()->route('admin.item.process', $itemId)->with('success', 'Saved successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    public function deleteRoll($rollId)
    {
        $roll = \App\Models\Roll::with('item.purchaseOrder')->findOrFail($rollId);

        if ($roll->qr_code_path && \Storage::exists($roll->qr_code_path)) {
            \Storage::delete($roll->qr_code_path);
        }

        $itemId = $roll->item->id;
        $roll->delete();

        return response()->json(['success' => true, 'message' => 'Roll deleted successfully', 'item_id' => $itemId]);
    }

    public function markItemProcessed($itemId)
    {
        $item = \App\Models\PoItem::findOrFail($itemId);
        $poId = $item->purchaseOrder->id;
        
        try {
            $item->markAsProcessed();
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.purchase-order.view', $poId)->with('success', 'Item marked as processed successfully!');
    }

    public function markAsPartialShipment($itemId)
    {
        $item = \App\Models\PoItem::findOrFail($itemId);
        $poId = $item->purchaseOrder->id;

        try {
            $item->splitToPartialShipment();
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.purchase-order.view', $poId)
            ->with('success', 'Partial shipment processed! A follow-up batch has been created for the remaining quantity.');
    }

    public function revertProcessing($itemId)
    {
        $item = \App\Models\PoItem::findOrFail($itemId);
        $item->status = 'processing';
        $item->save();
        $item->purchaseOrder->updateStatusBasedOnItems();

        return redirect()->back()->with('success', 'Item reverted to processing status!');
    }

    public function generatePackingSlip(Request $request, $poId)
    {
        $purchaseOrder = PurchaseOrder::with(['items.rolls'])->findOrFail($poId);

        $request->validate([
            'items' => 'required|array',
            'items.*' => 'exists:po_items,id',
            'delivery_note' => 'required|string|max:255'
        ]);

        $packingSlip = \App\Models\PackingSlip::create([
            'po_id' => $poId,
            'vendor_id' => $purchaseOrder->vendor_id,
            'items' => $request->items,
            'delivery_note' => $request->delivery_note
        ]);

        \App\Models\Roll::whereIn('item_id', $request->items)->update(['is_printed' => true, 'printed_at' => now()]);

        return redirect()->route('admin.packing-slip.view', $packingSlip->id);
    }

    public function viewPackingSlip($id)
    {
        $packingSlip = \App\Models\PackingSlip::with(['purchaseOrder.vendor'])->findOrFail($id);
        return redirect()->route('admin.packing-slip.print', $packingSlip->id);
    }

    public function printPackingSlip($id)
    {
        $packingSlip = \App\Models\PackingSlip::with(['purchaseOrder.vendor'])->findOrFail($id);

        $selectedItems = \App\Models\PoItem::whereIn('id', $packingSlip->items)
            ->orderBy('item_number', 'asc')
            ->with([
                'rolls' => function ($query) {
                    $query->orderBy('sequence');
                }
            ])
            ->get();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.pdf.packing-slip', compact('packingSlip', 'selectedItems'));
        $packingSlip->markPrinted();

        return $pdf->stream('packing-slip-' . $packingSlip->slip_number . '.pdf');
    }

    public function quickGeneratePackingSlip(Request $request, $poId)
    {
        $purchaseOrder = PurchaseOrder::findOrFail($poId);

        $request->validate([
            'delivery_note' => 'required|string|max:255'
        ]);

        $completedItemIds = \App\Models\PoItem::where('po_id', $poId)->where('status', 'completed')->pluck('id')->toArray();

        if (empty($completedItemIds)) {
            return redirect()->back()->with('error', 'No completed items found.');
        }

        $packingSlip = \App\Models\PackingSlip::create([
            'po_id' => $poId,
            'vendor_id' => $purchaseOrder->vendor_id,
            'items' => $completedItemIds,
            'delivery_note' => $request->delivery_note
        ]);

        \App\Models\Roll::whereIn('item_id', $completedItemIds)->update(['is_printed' => true, 'printed_at' => now()]);

        return redirect()->route('admin.packing-slip.print', $packingSlip->id);
    }

    public function settings()
    {
        $settings = \App\Models\Setting::orderBy('group')->orderBy('key')->get();
        return view('admin.settings', compact('settings'));
    }

    public function updateSettings(Request $request)
    {
        $settings = $request->input('settings', []);

        foreach ($settings as $key => $value) {
            \App\Models\Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        return redirect()->back()->with('success', 'Settings updated successfully.');
    }
}