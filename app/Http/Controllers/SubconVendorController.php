<?php

namespace App\Http\Controllers;

use App\Models\SubconOrder;
use App\Services\SubconProductionService;
use Illuminate\Support\Facades\Auth;

class SubconVendorController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            if (Auth::user()->role !== 'subcon_vendor') {
                abort(403, 'Unauthorized access.');
            }

            return $next($request);
        });
    }

    public function dashboard()
    {
        $vendorId = Auth::user()->vendor_id;

        $stats = [
            'total_orders' => SubconOrder::where('vendor_id', $vendorId)->count(),
            'active_orders' => SubconOrder::where('vendor_id', $vendorId)
                ->whereIn('status', ['pending', 'in_progress'])->count(),
            'completed' => SubconOrder::where('vendor_id', $vendorId)
                ->where('status', 'completed')->count(),
        ];

        $recentOrders = SubconOrder::with('items')
            ->where('vendor_id', $vendorId)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return view('subcon.vendor.dashboard', compact('stats', 'recentOrders'));
    }

    public function orders()
    {
        $vendorId = Auth::user()->vendor_id;

        $orders = SubconOrder::with('items')
            ->where('vendor_id', $vendorId)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('subcon.vendor.orders.index', compact('orders'));
    }

    public function viewOrder(string $id, SubconProductionService $production)
    {
        $vendorId = Auth::user()->vendor_id;

        $order = SubconOrder::with('items')
            ->where('vendor_id', $vendorId)
            ->findOrFail($id);

        // Garment production detail backing this CMT PO (best-effort; [] if unlinked).
        $productionGroups = $production->forPo($order->order_number);
        $summary = $production->summarize($productionGroups);

        return view('subcon.vendor.orders.show', compact('order', 'productionGroups', 'summary'));
    }
}
