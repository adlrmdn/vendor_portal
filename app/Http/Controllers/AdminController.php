<?php

namespace App\Http\Controllers;

use App\Models\PoItem;
use App\Models\PurchaseOrder;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth; // Add this import
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Smalot\PdfParser\Parser as PdfParser;

class AdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');

        $this->middleware(function ($request, $next) {
            if (! in_array(Auth::user()->role, ['admin', 'fabric_admin'])) {
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
            'total_vendors' => Vendor::where('type', 'fabric')->where('is_active', true)->count(),
        ];

        $recentOrders = PurchaseOrder::with(['vendor'])
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
            ->with('items:id,po_id,status')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return view('admin.dashboard', compact('stats', 'recentOrders'));
    }

    public function purchaseOrders(Request $request)
    {
        // Sticky filters: remember the last-used search/filter so they survive
        // navigating into a PO and back (in-app "Back" button, breadcrumb, sidebar).
        $filterKeys = ['search', 'status', 'vendor_id', 'per_page'];
        if ($request->has('reset')) {
            $request->session()->forget('admin_po_filters');

            return redirect()->route('admin.purchase-orders');
        }
        if ($request->hasAny($filterKeys)) {
            $request->session()->put('admin_po_filters', $request->only($filterKeys));
        } elseif ($request->session()->has('admin_po_filters')) {
            $request->merge($request->session()->get('admin_po_filters'));
        }

        $search = $request->input('search');
        $status = $request->input('status');
        $vendorId = $request->input('vendor_id');

        $query = PurchaseOrder::with(['vendor'])
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

        if ($vendorId) {
            $query->where('vendor_id', $vendorId);
        }

        $perPage = (int) $request->get('per_page', 25);
        if (! in_array($perPage, [10, 25, 50])) {
            $perPage = 25;
        }

        $purchaseOrders = $query->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->appends($request->all());
        $vendors = Vendor::where('type', 'fabric')->where('is_active', true)->orderBy('name')->get();

        return view('admin.purchase-orders', compact('purchaseOrders', 'vendors', 'search', 'status', 'vendorId', 'perPage'));
    }

    public function viewPurchaseOrder($id)
    {
        $purchaseOrder = PurchaseOrder::with([
            'vendor',
            'items' => function ($query) {
                $query->with(['rolls']);
            },
        ])->findOrFail($id);

        return view('admin.purchase-order-view', compact('purchaseOrder'));
    }

    public function vendors()
    {
        $vendors = Vendor::where('type', 'fabric')
            ->withCount(['purchaseOrders', 'activePurchaseOrders'])
            ->orderBy('name')
            ->paginate(20);

        return view('admin.vendors', compact('vendors'));
    }

    public function vendorDetail($id)
    {
        // Scope to fabric: a fabric admin must never act on a subcon vendor.
        $vendor = Vendor::where('type', 'fabric')->with([
            'purchaseOrders' => function ($query) {
                $query->orderBy('created_at', 'desc');
            },
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
            'type' => 'fabric',
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
        $vendor = Vendor::where('type', 'fabric')->findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'vendor_code' => 'required|string|max:50|unique:vendors,vendor_code,'.$id,
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
            },
        ])->findOrFail($itemId);

        // Admin can access any item
        return view('admin.process-item', compact('item'));
    }

    public function saveItemRolls(Request $request, $itemId)
    {
        $item = \App\Models\PoItem::with(['purchaseOrder', 'rolls'])->findOrFail($itemId);

        // Validation (Same as Vendor): all rolls carry the item's original order
        // metric as their unit; every metric column can be filled but only the
        // order metric is mandatory. Non-metric order units (PCS/UNIT) keep
        // their qty in the weight column.
        $orderUnit = strtoupper($item->unit);
        $primaryField = match ($orderUnit) {
            'YD' => 'length_yd',
            'M' => 'length_m',
            default => 'weight',
        };

        $validator = \Validator::make($request->all(), [
            'rolls' => 'required|array|min:1',
        ]);

        $validator->after(function ($validator) use ($request, $orderUnit, $primaryField) {
            $rowNo = 0;
            foreach ($request->input('rolls', []) as $key => $roll) {
                $rowNo++;
                if (isset($roll['delete']) && $roll['delete'] == '1') {
                    continue;
                }

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

                foreach (['length_yd' => 'YD', 'length_m' => 'M', 'weight' => 'KG'] as $field => $label) {
                    if ($field !== $primaryField && isset($roll[$field]) && $roll[$field] !== '' && (! is_numeric($roll[$field]) || $roll[$field] < 0)) {
                        $validator->errors()->add("rolls.$key.$field", "Roll $rowNo: the $label value must be a valid non-negative number.");
                    }
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
                if (isset($data['delete']) && $data['delete'] == '1') {
                    continue;
                }
                if (isset($data['id'])) {
                    $inputMap[$data['id']] = $data;
                } else {
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

            // A. Update Existing
            foreach ($existingRolls as $existingRoll) {
                if (isset($inputMap[$existingRoll->id])) {
                    $data = $inputMap[$existingRoll->id];

                    $updateData = $metricData($data) + [
                        'sequence' => $nextSequence,
                    ];

                    $rollNumber = sprintf('%s-%s-%03d', $item->purchaseOrder->po_number, $item->item_number, $nextSequence);
                    $updateData['roll_number'] = $rollNumber;

                    $existingRoll->update($updateData);
                    $nextSequence++;
                    $updatedCount++;
                }
            }

            // B. Create New
            foreach ($rollsData as $data) {
                if ((isset($data['delete']) && $data['delete'] == '1') || isset($data['id'])) {
                    continue;
                }

                $createData = $metricData($data) + [
                    'item_id' => $item->id,
                    'sequence' => $nextSequence,
                    'grade' => 'A',
                    'defects' => [],
                    'notes' => '',
                ];

                $rollNumber = sprintf('%s-%s-%03d', $item->purchaseOrder->po_number, $item->item_number, $nextSequence);
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

            return redirect()->back()->with('error', 'Error: '.$e->getMessage());
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
            // Admin bypasses the delivery-tolerance restriction (no amend-request flow on admin side).
            $item->markAsProcessed(false);
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

    // Upload rolls data via Excel/PDF (admin mirror of VendorController@uploadRollsData; no vendor scoping).
    public function uploadRollsData(Request $request, $itemId)
    {
        $item = PoItem::with(['purchaseOrder'])->findOrFail($itemId);

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

    // Download the uniform Excel template for the rolls import (admin mirror).
    public function downloadRollsTemplate($itemId)
    {
        $item = PoItem::with('purchaseOrder')->findOrFail($itemId);

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

    public function generatePackingSlip(Request $request, $poId)
    {
        $purchaseOrder = PurchaseOrder::with(['items.rolls'])->findOrFail($poId);

        $request->validate([
            'items' => 'required|array',
            'items.*' => 'exists:po_items,id',
            'delivery_note' => 'required|string|max:255',
        ]);

        $packingSlip = \App\Models\PackingSlip::create([
            'po_id' => $poId,
            'vendor_id' => $purchaseOrder->vendor_id,
            'items' => $request->items,
            'delivery_note' => $request->delivery_note,
        ]);

        \App\Models\Roll::whereIn('item_id', $request->items)->update(['is_printed' => true, 'printed_at' => now()]);

        return redirect()->route('admin.packing-slip.view', [
            'id' => $packingSlip->id,
            'show_secondary' => $request->input('show_secondary', 0),
            'reverse_units' => $request->input('reverse_units', 0),
        ]);
    }

    public function viewPackingSlip($id)
    {
        $packingSlip = \App\Models\PackingSlip::with(['purchaseOrder.vendor'])->findOrFail($id);

        return redirect()->route('admin.packing-slip.print', [
            'id' => $packingSlip->id,
            'show_secondary' => request()->query('show_secondary', 0),
            'reverse_units' => request()->query('reverse_units', 0),
        ]);
    }

    public function printPackingSlip($id)
    {
        $packingSlip = \App\Models\PackingSlip::with(['purchaseOrder.vendor'])->findOrFail($id);

        $selectedItems = \App\Models\PoItem::whereIn('id', $packingSlip->items)
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

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView($view, compact('packingSlip', 'selectedItems', 'showSecondary', 'reverseUnits'));
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

        return 'admin.pdf.packing-slip';
    }

    public function quickGeneratePackingSlip(Request $request, $poId)
    {
        $purchaseOrder = PurchaseOrder::findOrFail($poId);

        $request->validate([
            'delivery_note' => 'required|string|max:255',
        ]);

        $completedItemIds = \App\Models\PoItem::where('po_id', $poId)->where('status', 'completed')->pluck('id')->toArray();

        if (empty($completedItemIds)) {
            return redirect()->back()->with('error', 'No completed items found.');
        }

        $packingSlip = \App\Models\PackingSlip::create([
            'po_id' => $poId,
            'vendor_id' => $purchaseOrder->vendor_id,
            'items' => $completedItemIds,
            'delivery_note' => $request->delivery_note,
        ]);

        \App\Models\Roll::whereIn('item_id', $completedItemIds)->update(['is_printed' => true, 'printed_at' => now()]);

        return redirect()->route('admin.packing-slip.print', [
            'id' => $packingSlip->id,
            'show_secondary' => $request->input('show_secondary', 0),
            'reverse_units' => $request->input('reverse_units', 0),
        ]);
    }

    public function settings()
    {
        // Fabric settings only — subcon settings (group "subcon" / subcon_* keys)
        // are managed on the subcon admin Workflow page.
        $settings = \App\Models\Setting::where(function ($query) {
            $query->whereNull('group')->orWhere('group', '!=', 'subcon');
        })
            ->where('key', 'not like', 'subcon_%')
            ->orderBy('group')
            ->orderBy('key')
            ->get();

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
