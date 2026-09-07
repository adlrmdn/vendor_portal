<?php

namespace App\Http\Controllers;

use App\Models\PoItem;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth; // Add this import
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

    public function vendors(Request $request)
    {
        $query = Vendor::where('type', 'fabric')
            ->withCount(['purchaseOrders', 'activePurchaseOrders'])
            ->with(['users' => function ($q) {
                $q->where('role', 'fabric_vendor');
            }]);

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $term = '%'.strtolower($search).'%';
                $q->whereRaw('LOWER(name) LIKE ?', [$term])
                  ->orWhereRaw('LOWER(vendor_code) LIKE ?', [$term])
                  ->orWhereRaw('LOWER("group") LIKE ?', [$term])
                  ->orWhereHas('users', function ($userQuery) use ($term) {
                      $userQuery->whereRaw('LOWER(email) LIKE ?', [$term]);
                  });
            });
        }

        $vendors = $query->orderBy('name')
            ->paginate(20)
            ->withQueryString();

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
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'vendor_code' => 'required|string|max:50|unique:vendors,vendor_code',
            'group' => 'nullable|string|max:100',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        // 'id' isn't mass-assignable on Vendor (no creating hook either), so
        // Vendor::create(['id' => ...]) silently drops it and the DB assigns
        // its own id instead — harmless here (nothing else depends on it
        // matching), but set it directly for correctness.
        $vendorId = (string) Str::uuid();
        $loginEmail = $this->deriveVendorLoginEmail($data['name']);
        // Basic shared default password — the vendor is expected to change it.
        $password = 'password';

        DB::transaction(function () use ($request, $data, $vendorId, $loginEmail, $password) {
            $vendor = new Vendor([
                'name' => $data['name'],
                'vendor_code' => $data['vendor_code'],
                'group' => $data['group'] ?? null,
                'type' => 'fabric',
                'is_active' => $request->has('is_active'),
                'contact_info' => [
                    'contact_person' => $data['contact_person'] ?? null,
                    'email' => $data['email'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'address' => $data['address'] ?? null,
                ],
            ]);
            $vendor->id = $vendorId;
            $vendor->save();

            $user = new User([
                'name' => $data['name'],
                'email' => $loginEmail,
                'password' => bcrypt($password),
                'role' => 'fabric_vendor',
                'vendor_id' => $vendorId,
            ]);
            $user->id = (string) Str::uuid();
            $user->save();
        });

        // Pull this vendor's existing confirmed POs from D365 right away, off
        // the request cycle, instead of leaving it empty until the next
        // scheduled d365:sync-orders run.
        \App\Jobs\SyncNewFabricVendorOrders::dispatch($data['vendor_code']);

        // Flash the plaintext password once — it is never stored in readable form,
        // so this is the only chance to copy it. Shown in a modal on redirect.
        return redirect()->route('admin.vendors')
            ->with('success', 'Vendor "'.$data['name'].'" created with a portal login.')
            ->with('new_vendor_credentials', [
                'name' => $data['name'],
                'login_email' => $loginEmail,
                'password' => $password,
            ]);
    }

    /**
     * Derive a unique, readable portal login email from a vendor name, matching
     * the same "vendor@{abbreviated-name}.com" convention already used by
     * `vendors:register-fabric-accounts` and the D365 fabric vendor sync, so
     * admin-created accounts don't drift into a second naming scheme.
     */
    private function deriveVendorLoginEmail(string $name): string
    {
        $words = array_filter(explode(' ', strtolower($name)));
        $prefixes = ['pt', 'cv', 'fa', 'ud', 'pd', 'koperasi'];

        if (count($words) > 1 && in_array(reset($words), $prefixes, true)) {
            array_shift($words);
        }

        if (empty($words)) {
            $slug = 'fabric';
        } else {
            $concat = preg_replace('/[^a-z0-9]/', '', implode('', $words));
            if (strlen($concat) <= 15) {
                $slug = $concat;
            } else {
                $words = array_values($words);
                $firstWord = preg_replace('/[^a-z0-9]/', '', $words[0]);
                $initials = '';
                for ($i = 1; $i < count($words); $i++) {
                    $initials .= preg_replace('/[^a-z0-9]/', '', substr($words[$i], 0, 1));
                }
                $slug = substr($firstWord.$initials, 0, 15);
            }
        }

        $email = "vendor@{$slug}.com";
        $counter = 1;
        while (User::where('email', $email)->exists()) {
            $email = "vendor@{$slug}{$counter}.com";
            $counter++;
        }

        return $email;
    }

    public function resetVendorPassword(Request $request, string $id)
    {
        $vendor = Vendor::where('type', 'fabric')->findOrFail($id);

        $data = $request->validate([
            'password' => 'nullable|string|min:6|max:255',
        ]);

        $password = $data['password'] ?? 'password';

        $users = User::where('vendor_id', $vendor->id)
            ->where('role', 'fabric_vendor')
            ->get();

        if ($users->isEmpty()) {
            $loginEmail = $this->deriveVendorLoginEmail($vendor->name);
            $user = new User([
                'name' => $vendor->name,
                'email' => $loginEmail,
                'password' => bcrypt($password),
                'role' => 'fabric_vendor',
                'vendor_id' => $vendor->id,
            ]);
            $user->id = (string) Str::uuid();
            $user->save();
            $email = $loginEmail;
        } else {
            foreach ($users as $user) {
                $user->password = bcrypt($password);
                $user->save();
            }
            $email = $users->first()->email;
        }

        return redirect()->route('admin.vendors', $request->only('search'))
            ->with('success', 'Password reset successfully for vendor "'.$vendor->name.'".')
            ->with('new_vendor_credentials', [
                'name' => $vendor->name,
                'login_email' => $email,
                'password' => $password,
                'is_reset' => true,
            ]);
    }

    public function createVendorAccount(Request $request, string $id)
    {
        $vendor = Vendor::where('type', 'fabric')->findOrFail($id);

        $existingUser = User::where('vendor_id', $vendor->id)
            ->where('role', 'fabric_vendor')
            ->first();

        if ($existingUser) {
            return redirect()->route('admin.vendors', $request->only('search'))
                ->with('warning', 'Vendor already has a portal login account ('.$existingUser->email.').');
        }

        $loginEmail = $this->deriveVendorLoginEmail($vendor->name);
        $password = 'password';

        $user = new User([
            'name' => $vendor->name,
            'email' => $loginEmail,
            'password' => bcrypt($password),
            'role' => 'fabric_vendor',
            'vendor_id' => $vendor->id,
        ]);
        $user->id = (string) Str::uuid();
        $user->save();

        return redirect()->route('admin.vendors', $request->only('search'))
            ->with('success', 'Portal login account created for vendor "'.$vendor->name.'".')
            ->with('new_vendor_credentials', [
                'name' => $vendor->name,
                'login_email' => $loginEmail,
                'password' => $password,
            ]);
    }

    public function toggleVendorStatus(string $id)
    {
        $vendor = Vendor::where('type', 'fabric')->findOrFail($id);
        $vendor->is_active = ! $vendor->is_active;
        $vendor->save();

        $status = $vendor->is_active ? 'activated' : 'deactivated';

        return redirect()->route('admin.vendors')->with('success', 'Vendor '.$status.' successfully.');
    }

    public function deleteVendor(string $id)
    {
        $vendor = Vendor::where('type', 'fabric')->findOrFail($id);

        $poCount = PurchaseOrder::where('vendor_id', $vendor->id)->count();
        if ($poCount > 0) {
            return redirect()->route('admin.vendors')
                ->with('error', 'Cannot delete vendor "'.$vendor->name.'" — it has '.$poCount.' purchase order(s) on file. Deactivate it instead.');
        }

        DB::transaction(function () use ($vendor) {
            // Delete associated portal login users to avoid orphans.
            User::where('vendor_id', $vendor->id)->delete();
            $vendor->delete();
        });

        return redirect()->route('admin.vendors')->with('success', 'Vendor "'.$vendor->name.'" deleted.');
    }

    public function updateVendor(Request $request, $id)
    {
        $vendor = Vendor::where('type', 'fabric')->findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'vendor_code' => 'required|string|max:50|unique:vendors,vendor_code,'.$id,
            'group' => 'nullable|string|max:100',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $contactInfo = $vendor->contact_info ?? [];
        $contactInfo['contact_person'] = $data['contact_person'] ?? null;
        $contactInfo['email'] = $data['email'] ?? null;
        $contactInfo['phone'] = $data['phone'] ?? null;
        $contactInfo['address'] = $data['address'] ?? null;

        $vendor->update([
            'name' => $data['name'],
            'vendor_code' => $data['vendor_code'],
            'group' => $data['group'] ?? null,
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
                // Deleted rows must not consume a row number — the UI's "Roll
                // N" labels only count non-deleted rows, and this counter has
                // to match that exactly or the error points at the wrong card.
                if (isset($roll['delete']) && $roll['delete'] == '1') {
                    continue;
                }
                $rowNo++;

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
                        $roll->delete();
                        $deletedCount++;
                    }
                }
            }

            // 2. Process Updates and Creates (Re-sequencing)
            $existingRolls = $item->rolls()->where('item_id', $item->id)->orderBy('sequence')->orderBy('created_at')->get();
            $nextSequence = \App\Models\Roll::siblingSequenceOffset($item) + 1;
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

                    $rollNumber = \App\Models\Roll::buildRollNumber($item->purchaseOrder->po_number, $item->item_number, $nextSequence);
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

                $rollNumber = \App\Models\Roll::buildRollNumber($item->purchaseOrder->po_number, $item->item_number, $nextSequence);
                $createData['roll_number'] = $rollNumber;

                \App\Models\Roll::create($createData);

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

        $itemId = $roll->item->id;
        $roll->delete();

        return response()->json(['success' => true, 'message' => 'Roll deleted successfully', 'item_id' => $itemId]);
    }

    public function rollQrCode($rollId)
    {
        $roll = \App\Models\Roll::with('item.purchaseOrder')->findOrFail($rollId);

        return view('rolls.qr-view', [
            'roll' => $roll,
            'qrSvg' => \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(260)->generate($roll->qrPayload()),
            'qtyCaption' => $roll->qtyCaption(),
        ]);
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
        // General settings only — subcon settings (group "subcon" / subcon_* keys)
        // are managed on the subcon admin Workflow page, and fabric approval
        // routing (group "fabric") is managed on the fabric admin Workflow page.
        // whereNull('group') is required alongside the exclusion: `group != x`
        // never matches NULL rows in SQL, so ungrouped settings would silently
        // disappear from this page without it.
        $settings = \App\Models\Setting::where(function ($query) {
            $query->whereNull('group')->orWhereNotIn('group', ['subcon', 'fabric']);
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

    /**
     * Fabric tolerance-amendment / partial-shipment requests awaiting a
     * decision. Mirrors the subcon admin Approvals tab — lets the admin act
     * in-app instead of relying solely on the signed email link.
     */
    public function approvals(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $query = \App\Models\ToleranceAmendmentRequest::with('poItem.purchaseOrder.vendor')
            ->where('status', 'pending');

        if ($search !== '') {
            $driver = DB::connection()->getDriverName();
            $likeOperator = $driver === 'pgsql' ? 'ilike' : 'like';

            $query->whereHas('poItem', function ($q) use ($search, $likeOperator) {
                $q->where('item_number', $likeOperator, '%'.$search.'%')
                    ->orWhereHas('purchaseOrder', function ($poQuery) use ($search, $likeOperator) {
                        $poQuery->where('po_number', $likeOperator, '%'.$search.'%')
                            ->orWhereHas('vendor', function ($vendorQuery) use ($search, $likeOperator) {
                                $vendorQuery->where('name', $likeOperator, '%'.$search.'%');
                            });
                    });
            });
        }

        $requests = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        return view('admin.approvals', compact('requests', 'search'));
    }

    /**
     * Fabric admin Workflow settings — the static email address(es) that
     * receive tolerance-amendment / partial-shipment approval requests.
     */
    public function workflow()
    {
        $fabricApproverEmail = \App\Models\Setting::getValue('fabric_approver_email', 'leon@megaperintis.co.id');

        return view('admin.workflow', compact('fabricApproverEmail'));
    }

    public function updateWorkflow(Request $request)
    {
        $multiEmail = function (string $attribute, $value, $fail) {
            foreach (preg_split('/[,;]+/', (string) $value) as $email) {
                $email = trim($email);
                if ($email === '') {
                    continue;
                }
                if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $fail("The {$attribute} field contains an invalid email address: {$email}");
                }
            }
        };

        $request->validate([
            'fabric_approver_email' => ['required', $multiEmail],
        ]);

        \App\Models\Setting::updateOrCreate(
            ['key' => 'fabric_approver_email'],
            [
                'value' => $request->input('fabric_approver_email'),
                'group' => 'fabric',
                'type' => 'string',
                'description' => 'Email address(es) that receive fabric tolerance-amendment and partial-shipment approval requests (comma-separated for multiple)',
            ]
        );

        return redirect()->back()->with('success', 'Workflow settings updated successfully.');
    }
}
