<?php

namespace App\Http\Controllers;

use App\Models\PcFabricItem;
use App\Models\PcPlmRef;
use App\Models\Vendor;
use App\Services\PcFdrLookupService;
use Illuminate\Http\Request;

/**
 * Thin JSON wrappers around PcFabricItem::scopeSearch()/PcPlmRef::scopeSearch()
 * (already built in Phase 1) for Tom Select's AJAX `load:` callback — the
 * first use of that mode in this app (every other .searchable select so
 * far just filters options already in the DOM).
 */
class PcContractSearchController extends Controller
{
    public function searchFabricItems(Request $request)
    {
        $term = (string) $request->get('q', '');
        $items = $term === ''
            ? PcFabricItem::active()->orderBy('item_name')->limit(50)->get(['id', 'item_number', 'item_name', 'uom_code'])
            : PcFabricItem::active()->search($term)->limit(50)->get(['id', 'item_number', 'item_name', 'uom_code']);

        return response()->json($items->map(fn ($i) => [
            'id' => $i->id,
            'text' => "{$i->item_name} ({$i->item_number})",
            'item_number' => $i->item_number,
            'uom_code' => $i->uom_code,
        ]));
    }

    public function searchPlmRefs(Request $request)
    {
        $term = (string) $request->get('q', '');
        $refs = $term === ''
            ? PcPlmRef::orderBy('plm_id')->limit(50)->get(['id', 'plm_id', 'article_name', 'season'])
            : PcPlmRef::search($term)->limit(50)->get(['id', 'plm_id', 'article_name', 'season']);

        return response()->json($refs->map(fn ($r) => [
            'id' => $r->id,
            'text' => "{$r->plm_id} — {$r->article_name}",
            'season' => $r->season,
        ]));
    }

    /**
     * Supplementary, non-binding FDR reference panel for a line — see
     * PcFdrLookupService's docblock. Never required, never auto-filled.
     */
    public function fdrLookup(Request $request, PcFdrLookupService $fdr)
    {
        $itemNumber = (string) $request->get('item_number', '');
        $vendorId = (string) $request->get('vendor_id', '');

        if ($itemNumber === '' || $vendorId === '') {
            return response()->json(null);
        }

        $vendorCode = Vendor::where('id', $vendorId)->value('vendor_code');
        if (! $vendorCode) {
            return response()->json(null);
        }

        return response()->json($fdr->findForItemAndVendor($itemNumber, $vendorCode));
    }
}
