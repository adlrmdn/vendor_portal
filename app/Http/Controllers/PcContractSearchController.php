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
        $term = trim((string) $request->get('q', ''));
        $items = $term === ''
            ? PcFabricItem::active()->orderBy('item_name')->limit(50)->get(['id', 'item_number', 'item_name', 'uom_code'])
            : PcFabricItem::active()->search($term)->limit(50)->get(['id', 'item_number', 'item_name', 'uom_code']);

        if ($items->count() < 20 && class_exists(\App\Models\PoItem::class)) {
            $existingNumbers = $items->pluck('item_number')->filter()->all();
            $poQuery = \App\Models\PoItem::query()
                ->select(['item_number', 'description', 'unit', 'fabric_type'])
                ->whereNotNull('item_number')
                ->where('item_number', '!=', '');

            if (!empty($existingNumbers)) {
                $poQuery->whereNotIn('item_number', $existingNumbers);
            }

            if ($term !== '') {
                $words = array_filter(preg_split('/\s+/', $term));
                foreach ($words as $word) {
                    $pattern = '%' . strtolower($word) . '%';
                    $poQuery->where(function ($q) use ($pattern) {
                        $q->whereRaw('LOWER(item_number) LIKE ?', [$pattern])
                          ->orWhereRaw('LOWER(description) LIKE ?', [$pattern]);
                    });
                }
            }

            $poItems = $poQuery->distinct('item_number')->limit(50)->get();

            foreach ($poItems as $pi) {
                $fabricItem = PcFabricItem::firstOrCreate(
                    ['item_number' => $pi->item_number],
                    [
                        'item_name' => $pi->description ?: $pi->item_number,
                        'uom_code' => $pi->unit ?: 'MTR',
                        'weave' => $pi->fabric_type,
                        'is_active' => true,
                    ]
                );
                $items->push($fabricItem);
            }
        }

        return response()->json($items->take(50)->map(fn ($i) => [
            'id' => $i->id,
            'text' => ($i->item_name && $i->item_name !== $i->item_number)
                ? "{$i->item_name} ({$i->item_number})"
                : $i->item_number,
            'item_number' => $i->item_number,
            'uom_code' => $i->uom_code,
        ]));
    }

    public function searchPlmRefs(Request $request)
    {
        $term = trim((string) $request->get('q', ''));
        $fabricItemId = (string) $request->get('fabric_item_id', '');
        $itemNumber = (string) $request->get('item_number', '');
        $includeCompleted = $request->boolean('include_completed', false);

        if (! $itemNumber && $fabricItemId !== '') {
            $itemNumber = PcFabricItem::where('id', $fabricItemId)->value('item_number') ?: '';
        }

        // 1. Identify completed/finished PLMs (styles where all po_items are status='completed')
        $completedPlms = [];
        if (! $includeCompleted && class_exists(\App\Models\PoItem::class)) {
            $completedPlms = \App\Models\PoItem::select('plm_number')
                ->whereNotNull('plm_number')->where('plm_number', '!=', '')
                ->groupBy('plm_number')
                ->havingRaw("COUNT(CASE WHEN status != 'completed' THEN 1 END) = 0")
                ->pluck('plm_number')
                ->toArray();
        }

        // 2. Identify active (in-progress/pending) PLMs that use this fabric item
        $activePlmsForFabric = [];
        if ($itemNumber !== '' && class_exists(\App\Models\PoItem::class)) {
            $activePlmsForFabric = \App\Models\PoItem::where('item_number', $itemNumber)
                ->whereNotNull('plm_number')->where('plm_number', '!=', '')
                ->where('status', '!=', 'completed')
                ->orderBy('created_at', 'desc')
                ->pluck('plm_number')
                ->unique()
                ->values()
                ->all();
        }

        // 3. Query PcPlmRef
        $query = PcPlmRef::query();
        if (! empty($completedPlms)) {
            $query->whereNotIn('plm_id', $completedPlms);
        }

        if ($term !== '') {
            $query->search($term);
        }

        // Prioritize active PLMs using this fabric item to the top
        if (! empty($activePlmsForFabric)) {
            $placeholders = implode(',', array_fill(0, count($activePlmsForFabric), '?'));
            $query->orderByRaw("CASE WHEN plm_id IN ($placeholders) THEN 0 ELSE 1 END", $activePlmsForFabric);
        }

        $refs = $query->orderBy('plm_id')->limit(50)->get(['id', 'plm_id', 'article_name', 'season']);

        return response()->json($refs->map(function ($r) use ($activePlmsForFabric) {
            $isRecommended = in_array($r->plm_id, $activePlmsForFabric, true);
            return [
                'id' => $r->id,
                'plm_id' => $r->plm_id,
                'article_name' => $r->article_name,
                'season' => $r->season,
                'is_recommended' => $isRecommended,
                'text' => ($isRecommended ? '⭐ [Recommended] ' : '') . "{$r->plm_id} — {$r->article_name}" . ($r->season ? " ({$r->season})" : ""),
            ];
        }));
    }

    /**
     * Fetch detailed specs, last PO purchase price, and FDR info for a selected fabric item.
     */
    public function itemDetails(Request $request, PcFdrLookupService $fdrService)
    {
        $itemId = (string) $request->get('item_id', '');
        $itemNumber = (string) $request->get('item_number', '');
        $vendorId = (string) $request->get('vendor_id', '');

        $item = null;
        if ($itemId !== '') {
            $item = PcFabricItem::find($itemId);
        }
        if (! $item && $itemNumber !== '') {
            $item = PcFabricItem::where('item_number', $itemNumber)->first();
        }
        if (! $item && $itemNumber !== '' && class_exists(\App\Models\PoItem::class)) {
            $pi = \App\Models\PoItem::where('item_number', $itemNumber)->first();
            if ($pi) {
                $item = PcFabricItem::firstOrCreate(
                    ['item_number' => $pi->item_number],
                    [
                        'item_name' => $pi->description ?: $pi->item_number,
                        'uom_code' => $pi->unit ?: 'MTR',
                        'weave' => $pi->fabric_type,
                        'is_active' => true,
                    ]
                );
            }
        }

        if (! $item) {
            return response()->json(['success' => false, 'message' => 'Item not found'], 404);
        }

        $specs = $this->extractSpecs($item);

        // Check for latest active (pending or processing) PO item using this fabric
        $recommendedPlm = null;
        if (class_exists(\App\Models\PoItem::class)) {
            $activePoItem = \App\Models\PoItem::where('item_number', $item->item_number)
                ->whereNotNull('plm_number')->where('plm_number', '!=', '')
                ->where('status', '!=', 'completed')
                ->latest('created_at')
                ->first();

            if ($activePoItem) {
                $matchedPlm = PcPlmRef::where('plm_id', $activePoItem->plm_number)->first();
                if ($matchedPlm) {
                    $recommendedPlm = [
                        'plm_id' => $matchedPlm->id,
                        'plm_number' => $matchedPlm->plm_id,
                        'article_name' => $matchedPlm->article_name,
                        'season' => $matchedPlm->season,
                        'status' => $activePoItem->status,
                        'is_completed' => false,
                        'text' => "{$matchedPlm->plm_id} — {$matchedPlm->article_name}" . ($matchedPlm->season ? " ({$matchedPlm->season})" : ""),
                    ];
                }
            }
        }

        // Last PO purchase info (price history)
        $lastPo = null;
        if (class_exists(\App\Models\PoItem::class)) {
            $poItem = \App\Models\PoItem::where('item_number', $item->item_number)
                ->where('unit_price', '>', 0)
                ->with('purchaseOrder')
                ->latest('created_at')
                ->first();

            if ($poItem) {
                $matchedPlm = null;
                if (! empty($poItem->plm_number)) {
                    $matchedPlm = PcPlmRef::where('plm_id', $poItem->plm_number)->first();
                }

                $isCompleted = ($poItem->status === 'completed');

                $lastPo = [
                    'po_number' => $poItem->purchaseOrder?->po_number ?? (string) $poItem->po_id,
                    'unit_price' => (float) $poItem->unit_price,
                    'unit_price_formatted' => number_format((float) $poItem->unit_price, 2),
                    'currency' => $poItem->purchaseOrder?->currency ?? 'IDR',
                    'uom' => $poItem->unit ?? $item->uom_code,
                    'status' => $poItem->status,
                    'is_completed' => $isCompleted,
                    'plm_number' => $poItem->plm_number,
                    'plm_id' => $matchedPlm?->id,
                    'plm_text' => $matchedPlm ? "{$matchedPlm->plm_id} — {$matchedPlm->article_name}" . ($matchedPlm->season ? " ({$matchedPlm->season})" : "") : $poItem->plm_number,
                ];
            }
        }

        // FDR Lookup
        $fdr = null;
        if ($vendorId !== '') {
            $vendorCode = Vendor::where('id', $vendorId)->value('vendor_code');
            if ($vendorCode) {
                $fdr = $fdrService->findForItemAndVendor($item->item_number, $vendorCode);
            }
        }

        return response()->json([
            'success' => true,
            'id' => $item->id,
            'item_number' => $item->item_number,
            'item_name' => $item->item_name,
            'uom_code' => $item->uom_code ?: 'MTR',
            'specs' => $specs,
            'recommended_plm' => $recommendedPlm,
            'last_po' => $lastPo,
            'fdr' => $fdr,
        ]);
    }

    private function extractSpecs(PcFabricItem $item): array
    {
        $comp = $item->composition;
        $const = $item->construction;
        $width = $item->cut_width ?: $item->full_width;
        $weight = $item->weight_gsm ? ($item->weight_gsm . ' GSM') : null;
        $weave = $item->weave;

        $text = (string) $item->item_name;

        if (! $comp && preg_match('/\b(\d{1,3}%\s*[A-Z]+(?:\s*\d{1,3}%\s*[A-Z]+)*)\b/i', $text, $m)) {
            $comp = trim($m[1]);
        }
        if (! $const && preg_match('/\b(\d{1,3}[SX]?\s*[X*]\s*\d{1,3}[SX]?(?:\s*\/\s*\d{1,3}\s*[X*]\s*\d{1,3})?)\b/i', $text, $m)) {
            $const = trim($m[1]);
        }
        if (! $width && preg_match('/\b(\d{2}(?:\/\d{2})?\s*(?:\"|\'\')?(?:\s*CW|\s*CUT\s*WIDTH|\s*CM)?)\b/i', $text, $m)) {
            if (str_contains($m[1], '"') || str_contains($m[1], 'CW') || str_contains($m[1], '/')) {
                $width = trim($m[1]);
            }
        }
        if (! $weight && preg_match('/\b(\d+(?:\.\d+)?\s*(?:OZ|GSM|G\/M2))\b/i', $text, $m)) {
            $weight = trim($m[1]);
        }
        if (! $weave && preg_match('/\b(TWILL(?:\s*\d\/\d)?|POPLIN|DOBBY|OXFORD|SATIN|CANVAS|RIPSTOP|JERSEY|PIQUE|INTERLOCK|RIB)\b/i', $text, $m)) {
            $weave = trim($m[1]);
        }

        return array_filter([
            'composition' => $comp,
            'construction' => $const,
            'width' => $width,
            'weight' => $weight,
            'weave' => $weave,
        ]);
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
