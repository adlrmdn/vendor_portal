<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * The two fixed shipment-tolerance tables (WOVEN, KNIT), versioned by
 * effective date, always printed on every contract PDF (PC spec 6.1/9).
 * Frozen onto contract_tolerance at approve time.
 */
class ToleranceTemplate extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $primaryKey = 'id';

    protected $fillable = [
        'fabric_type', 'row_label', 'tolerance', 'highlight', 'sort_order', 'effective_from', 'effective_to', 'is_active',
    ];

    protected $casts = [
        'highlight' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeEffectiveOn($query, $date)
    {
        return $query->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date);
            });
    }

    public function scopeForFabricType($query, string $type)
    {
        return $query->where('fabric_type', $type)->orderBy('sort_order');
    }
}
