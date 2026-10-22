<?php

namespace App\Http\Controllers;

use App\Models\DeliveryTerm;
use App\Models\HsCode;
use App\Models\Lot;
use App\Models\Season;
use App\Models\TransportMode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PcMasterDataController extends Controller
{
    // PC is absorbed into the existing fabric admin area as a single tabbed
    // page (PcController::index()) — access is enforced by the same 'auth'
    // + 'role:admin,fabric_admin' middleware that already gates the rest of
    // /admin/* (routes/web.php). Any admin/fabric_admin can add to the 4
    // user-extensible master tables and manage seasons — same blanket
    // access as the rest of that area. Every action here only mutates and
    // redirects back to the Master Data tab; the read-only listing itself
    // is gathered by PcController::index().

    private function backToTab()
    {
        return redirect(route('admin.pc.data.index').'#master-data');
    }

    public function storeHsCode(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|size:10|unique:hs_codes,code',
            'description' => 'nullable|string|max:255',
        ]);

        HsCode::addedByUser($data, Auth::user());

        return $this->backToTab()->with('success', "HS Code \"{$data['code']}\" added.");
    }

    public function storeLot(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|max:20|unique:lots,code',
            'label' => 'nullable|string|max:255',
        ]);

        Lot::addedByUser($data, Auth::user());

        return $this->backToTab()->with('success', "Lot \"{$data['code']}\" added.");
    }

    public function storeTransportMode(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|max:20|unique:transport_modes,code',
            'label' => 'nullable|string|max:255',
        ]);

        TransportMode::addedByUser($data, Auth::user());

        return $this->backToTab()->with('success', "Mode of Transport \"{$data['code']}\" added.");
    }

    public function storeDeliveryTerm(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|max:50|unique:delivery_terms,code',
            'label' => 'nullable|string|max:255',
        ]);

        DeliveryTerm::addedByUser($data, Auth::user());

        return $this->backToTab()->with('success', "Terms of Delivery \"{$data['code']}\" added.");
    }

    public function storeSeason(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|max:255|unique:seasons,code',
            'label' => 'required|string|max:255',
            'sort_key' => 'nullable|integer',
        ]);

        $actor = Auth::user();

        Season::create($data + ['created_by' => $actor->id]);

        return $this->backToTab()->with('success', "Season \"{$data['code']}\" added.");
    }

    public function updateSeason(Request $request, Season $season)
    {
        $data = $request->validate([
            'label' => 'required|string|max:255',
            'is_open' => 'required|boolean',
            'sort_key' => 'nullable|integer',
        ]);

        $season->update($data);

        return $this->backToTab()->with('success', "Season \"{$season->code}\" updated.");
    }
}
