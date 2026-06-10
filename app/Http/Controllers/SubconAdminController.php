<?php

namespace App\Http\Controllers;

use App\Models\SubconOrder;
use App\Models\SubconOrderItem;
use App\Models\Vendor;
use App\Services\SubconProductionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SubconAdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            if (! in_array(Auth::user()->role, ['admin', 'subcon_admin'])) {
                abort(403, 'Unauthorized access.');
            }

            return $next($request);
        });
    }

    public function dashboard()
    {
        $stats = [
            'total_orders' => SubconOrder::count(),
            'active_orders' => SubconOrder::whereIn('status', ['pending', 'in_progress'])->count(),
            'completed' => SubconOrder::where('status', 'completed')->count(),
            'total_vendors' => Vendor::where('type', 'subcon')->where('is_active', true)->count(),
        ];

        $recentOrders = SubconOrder::with('vendor')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return view('subcon.admin.dashboard', compact('stats', 'recentOrders'));
    }

    public function vendors()
    {
        $vendors = Vendor::where('type', 'subcon')
            ->withCount('subconOrders')
            ->orderBy('name')
            ->paginate(20);

        return view('subcon.admin.vendors', compact('vendors'));
    }

    public function storeVendor(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'vendor_code' => 'required|string|max:50|unique:vendors,vendor_code',
            'group' => 'nullable|string|max:100',
            'contact_info.phone' => 'nullable|string|max:255',
            'contact_info.email' => 'nullable|email|max:255',
            'contact_info.address' => 'nullable|string|max:1000',
        ]);

        Vendor::create([
            'id' => Str::uuid()->toString(),
            'name' => $data['name'],
            'vendor_code' => $data['vendor_code'],
            'group' => $data['group'] ?? null,
            'type' => 'subcon',
            'contact_info' => array_filter([
                'phone' => $data['contact_info']['phone'] ?? null,
                'email' => $data['contact_info']['email'] ?? null,
                'address' => $data['contact_info']['address'] ?? null,
            ], fn ($v) => $v !== null),
            'is_active' => true,
        ]);

        return redirect()->route('subcon.admin.vendors')->with('success', 'Subcon vendor created.');
    }

    public function orders(Request $request)
    {
        $query = SubconOrder::with('vendor');

        if ($request->filled('vendor_id')) {
            $query->where('vendor_id', $request->vendor_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $query->where('order_number', 'like', '%'.$request->search.'%');
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate(20);
        $vendors = Vendor::where('type', 'subcon')->where('is_active', true)->orderBy('name')->get();

        return view('subcon.admin.orders.index', compact('orders', 'vendors'));
    }

    public function createOrder()
    {
        $vendors = Vendor::where('type', 'subcon')->where('is_active', true)->orderBy('name')->get();

        return view('subcon.admin.orders.create', compact('vendors'));
    }

    public function storeOrder(Request $request)
    {
        $data = $request->validate([
            'order_number' => 'required|string|max:100|unique:subcon_orders,order_number',
            'vendor_id' => ['required', 'uuid', Rule::exists('vendors', 'id')->where('type', 'subcon')],
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'order_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:order_date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_number' => 'required|string|max:100',
            'items.*.description' => 'required|string|max:1000',
            'items.*.quantity' => 'required|numeric|min:0.01|max:99999999.99',
            'items.*.unit' => 'required|string|max:20',
            'items.*.notes' => 'nullable|string|max:1000',
        ]);

        DB::beginTransaction();
        try {
            $order = SubconOrder::create([
                'id' => Str::uuid()->toString(),
                'order_number' => $data['order_number'],
                'vendor_id' => $data['vendor_id'],
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'order_date' => $data['order_date'],
                'due_date' => $data['due_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'pending',
            ]);

            foreach ($data['items'] as $item) {
                SubconOrderItem::create([
                    'id' => Str::uuid()->toString(),
                    'order_id' => $order->id,
                    'item_number' => $item['item_number'],
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'],
                    'status' => 'pending',
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withInput()->with('error', 'Failed to create order: '.$e->getMessage());
        }

        return redirect()->route('subcon.admin.orders')->with('success', 'Order created successfully.');
    }

    public function viewOrder(string $id, SubconProductionService $production)
    {
        $order = SubconOrder::with(['vendor', 'items'])->findOrFail($id);
        $productionGroups = $production->forPo($order->order_number);
        $summary = $production->summarize($productionGroups);

        return view('subcon.admin.orders.show', compact('order', 'productionGroups', 'summary'));
    }

    public function updateOrderStatus(Request $request, string $id)
    {
        $request->validate([
            'status' => 'required|in:pending,in_progress,completed,cancelled',
        ]);

        SubconOrder::findOrFail($id)->update(['status' => $request->status]);

        return back()->with('success', 'Order status updated.');
    }
}
