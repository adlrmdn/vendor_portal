<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * One marker/fabric-type block (e.g. SHELL, SOLID, a fabric code) of a
 * subcon order's cutting plan — the ratio-group cascade lives in `groups`.
 */
class SubconCuttingPlanBlock extends Model
{
    use HasFactory;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $primaryKey = 'id';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    protected $fillable = [
        'order_id',
        'marker_type',
        'cutt_width',
        'full_width',
        'gsm',
        'plan_date',
        'tolerance_pct',
        'kg_per_pc',
        'fabric_available_override',
        'notes',
        'groups',
        'sort_order',
    ];

    protected $casts = [
        'id' => 'string',
        'order_id' => 'string',
        'plan_date' => 'date',
        'tolerance_pct' => 'decimal:4',
        'kg_per_pc' => 'decimal:4',
        'fabric_available_override' => 'decimal:2',
        'groups' => 'array',
        'sort_order' => 'integer',
    ];

    public function order()
    {
        return $this->belongsTo(SubconOrder::class, 'order_id');
    }
}
