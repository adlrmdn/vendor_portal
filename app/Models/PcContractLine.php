<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PcContractLine extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $primaryKey = 'id';

    protected $fillable = [
        'contract_id', 'line_no', 'plm_ref_id', 'plm_id_snapshot', 'plm_article_name_snapshot',
        'fabric_item_id', 'item_number_snapshot', 'item_name_snapshot', 'uom_snapshot',
        'hs_code_id', 'hs_code_snapshot', 'qty', 'unit_price', 'remarks',
    ];

    protected $casts = [
        'line_no' => 'integer',
        'qty' => 'decimal:3',
        'unit_price' => 'decimal:4',
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

    public function contract()
    {
        return $this->belongsTo(PcContract::class, 'contract_id');
    }

    public function plmRef()
    {
        return $this->belongsTo(PcPlmRef::class, 'plm_ref_id');
    }

    public function fabricItem()
    {
        return $this->belongsTo(PcFabricItem::class, 'fabric_item_id');
    }

    public function hsCode()
    {
        return $this->belongsTo(HsCode::class, 'hs_code_id');
    }

    public function deliveries()
    {
        return $this->hasMany(PcContractDelivery::class, 'contract_line_id');
    }

    /**
     * INCLUDE -> unit_price / (1 + vat_rate/100); EXCLUDE (or no vat_rate
     * set) -> unit_price as-is. Pure function of this row + the parent
     * contract's vat_treatment/vat_rate — both immutable once the
     * contract leaves draft, so this never needs its own stored column.
     */
    public function getUnitPriceExVatAttribute(): string
    {
        $contract = $this->contract;

        if ($contract?->vatTreatment?->code === 'INCLUDE' && $contract->vat_rate) {
            return number_format((float) $this->unit_price / (1 + (float) $contract->vat_rate / 100), 4, '.', '');
        }

        return number_format((float) $this->unit_price, 4, '.', '');
    }

    public function getLineTotalAttribute(): string
    {
        return number_format((float) $this->qty * (float) $this->unit_price, 2, '.', '');
    }

    public function getLineTotalExVatAttribute(): string
    {
        return number_format((float) $this->qty * (float) $this->unit_price_ex_vat, 2, '.', '');
    }
}
