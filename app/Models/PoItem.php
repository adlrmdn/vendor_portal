<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PoItem extends Model
{
    use HasFactory;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $primaryKey = 'id';

    protected $table = 'po_items';

    protected $fillable = [
        'po_id',
        'item_number',
        'description',
        'batch',
        'line_number',
        'plm_number',
        'quantity',
        'underdelivery',
        'overdelivery',
        'unit',
        'unit_price',
        'total_price',
        'fabric_type',
        'color',
        'specifications',
        'status',
    ];

    protected $casts = [
        'id' => 'string',
        'po_id' => 'string',
        'specifications' => 'array',
        'quantity' => 'decimal:2',
        'underdelivery' => 'decimal:2',
        'overdelivery' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    /**
     * Per-roll delivered quantity in the roll's own unit. Rolls can now carry
     * every metric at once (YD + M + KG), so summing all columns would double
     * count — only the column matching the roll's unit counts.
     */
    public const ROLL_QUANTITY_SQL = "CASE WHEN unit = 'YD' THEN COALESCE(length_yd, 0) WHEN unit = 'M' THEN COALESCE(length_m, 0) ELSE COALESCE(weight, 0) END";

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_id');
    }

    public function rolls()
    {
        return $this->hasMany(Roll::class, 'item_id');
    }

    // NEW: Simple method to save rolls
    public function saveRolls($rollsData)
    {
        // Delete existing rolls first
        $this->rolls()->delete();

        $rolls = [];
        foreach ($rollsData as $index => $rollData) {
            $rollNumber = $this->purchaseOrder->po_number.'-'.
                $this->item_number.'-'.
                str_pad($index + 1, 3, '0', STR_PAD_LEFT);

            $roll = $this->rolls()->create([
                'roll_number' => $rollNumber,
                'sequence' => $index + 1,
                'weight' => $rollData['quantity'] ?? 0, // Using weight field for quantity
                'unit' => $rollData['unit'] ?? $this->unit,
                'grade' => null,
                'defects' => null,
            ]);

            $rolls[] = $roll;
        }

        return $rolls;
    }

    public function getOtherCompletedSiblingsDeliveredQuantity()
    {
        return (float) \DB::table('rolls')
            ->whereIn('item_id', self::where('po_id', $this->po_id)
                ->where('item_number', $this->item_number)
                ->where('status', 'completed')
                ->where('id', '!=', $this->id)
                ->pluck('id'))
            ->sum(\DB::raw(self::ROLL_QUANTITY_SQL));
    }

    /**
     * Min/max deliverable quantity for an arbitrary under/over tolerance pair,
     * so callers (amend-tolerance UI, approval email) can preview what a
     * proposed % actually means in the item's unit without duplicating the
     * global-target / other-siblings-delivered math.
     *
     * @return array{min: float, max: float}
     */
    public function getQuantityLimitsForTolerance(float $under, float $over): array
    {
        $target = $this->getGlobalOrderedQuantity();
        $otherDelivered = $this->getOtherCompletedSiblingsDeliveredQuantity();

        return [
            'min' => max(0, $target * (1 - $under / 100) - $otherDelivered),
            'max' => max(0, $target * (1 + $over / 100) - $otherDelivered),
        ];
    }

    public function getMinQuantityLimit()
    {
        return $this->getQuantityLimitsForTolerance($this->getEffectiveUnderdelivery(), $this->getEffectiveOverdelivery())['min'];
    }

    public function getMaxQuantityLimit()
    {
        return $this->getQuantityLimitsForTolerance($this->getEffectiveUnderdelivery(), $this->getEffectiveOverdelivery())['max'];
    }

    public function totalDeliveredQuantity()
    {
        return (float) $this->rolls()->sum(\DB::raw(self::ROLL_QUANTITY_SQL));
    }

    public function isQuantityWithinTolerance()
    {
        $currentDelivered = $this->totalDeliveredQuantity();
        $min = $this->getMinQuantityLimit();
        $max = $this->getMaxQuantityLimit();

        return $currentDelivered >= $min && $currentDelivered <= $max;
    }

    public function getGlobalOrderedQuantity()
    {
        return (float) self::where('po_id', $this->po_id)
            ->where('item_number', $this->item_number)
            ->sum('quantity');
    }

    public function getGlobalDeliveredQuantity()
    {
        $ids = self::where('po_id', $this->po_id)
            ->where('item_number', $this->item_number)
            ->pluck('id');

        return (float) \DB::table('rolls')
            ->whereIn('item_id', $ids)
            ->sum(\DB::raw(self::ROLL_QUANTITY_SQL));
    }

    public function getEffectiveUnderdelivery()
    {
        $under = (float) $this->underdelivery;

        return $under > 0 ? $under : (float) Setting::getValue('default_underdelivery', 3.00);
    }

    public function getEffectiveOverdelivery()
    {
        $over = (float) $this->overdelivery;

        return $over > 0 ? $over : (float) Setting::getValue('default_overdelivery', 3.00);
    }

    public function markAsProcessed(bool $enforceTolerance = true)
    {
        // Admins can finalize regardless of tolerance (they have no amend-request flow);
        // vendors still pass $enforceTolerance = true.
        if ($enforceTolerance && ! $this->isQuantityWithinTolerance()) {
            throw new \Exception('Quantity is outside the allowed delivery tolerance.');
        }

        $this->status = 'completed';
        $this->save();

        // Update PO status based on all items
        $this->purchaseOrder->updateStatusBasedOnItems();

        return $this;
    }

    public function splitToPartialShipment()
    {
        $deliveredQty = $this->totalDeliveredQuantity();
        $remainingQty = (float) $this->quantity - $deliveredQty;

        if ($remainingQty <= 0) {
            return $this->markAsProcessed();
        }

        return \DB::transaction(function () use ($deliveredQty, $remainingQty) {
            // 1. Create the shadow item
            $shadowBatch = $this->batch.'-P2';

            // Check if batch already exists, increment suffix if needed
            $count = 2;
            while (self::where('po_id', $this->po_id)
                ->where('item_number', $this->item_number)
                ->where('batch', $shadowBatch)
                ->exists()
            ) {
                $shadowBatch = $this->batch.'-P'.(++$count);
            }

            $shadow = $this->replicate();
            $shadow->id = \Illuminate\Support\Str::uuid();
            $shadow->quantity = $remainingQty;
            $shadow->batch = $shadowBatch;
            $shadow->status = 'pending';
            $shadow->total_price = $remainingQty * (float) $this->unit_price;
            $shadow->save();

            // 2. Update current item
            $this->quantity = $deliveredQty;
            $this->total_price = $deliveredQty * (float) $this->unit_price;
            $this->status = 'completed';
            $this->save();

            // 3. Update PO status
            $this->purchaseOrder->updateStatusBasedOnItems();

            return $this;
        });
    }

    public function hasApprovedPartialShipment()
    {
        return ToleranceAmendmentRequest::where('po_item_id', $this->id)
            ->where('type', 'partial_shipment')
            ->where('status', 'approved')
            ->exists();
    }
}
