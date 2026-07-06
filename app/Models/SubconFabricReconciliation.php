<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Leftover-fabric reconciliation for one fabric (by VSM label) on a subcon
 * order. Entered by the vendor on the cutting report; read by the HO form.
 */
class SubconFabricReconciliation extends Model
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
        'label',
        'short_roll',
        'sisa_kain',
        'kepala_kain',
        'retur_kain',
        // Consumption — filled by the subcon admin at cutting-report approval.
        'fabric_sent',
        'consumption_plan',
        'cutt_plan',
        'actual_consumption',
        'overconsumption',
        'fabric_price',
        'deduction',
    ];

    protected $casts = [
        'id' => 'string',
        'order_id' => 'string',
        'short_roll' => 'decimal:2',
        'sisa_kain' => 'decimal:2',
        'kepala_kain' => 'decimal:2',
        'retur_kain' => 'decimal:2',
        'fabric_sent' => 'decimal:2',
        'consumption_plan' => 'decimal:4',
        'cutt_plan' => 'integer',
        'actual_consumption' => 'decimal:4',
        'overconsumption' => 'decimal:4',
        'fabric_price' => 'decimal:2',
        'deduction' => 'decimal:2',
    ];

    public function order()
    {
        return $this->belongsTo(SubconOrder::class, 'order_id');
    }
}
