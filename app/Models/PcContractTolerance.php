<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Frozen once by PcContractFreezeService at confirm — never updated again. */
class PcContractTolerance extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $primaryKey = 'id';

    protected $fillable = [
        'contract_id', 'fabric_type', 'row_label', 'tolerance', 'highlight', 'sort_order', 'source_tolerance_template_id',
    ];

    protected $casts = [
        'highlight' => 'boolean',
        'sort_order' => 'integer',
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

    public function sourceTemplate()
    {
        return $this->belongsTo(ToleranceTemplate::class, 'source_tolerance_template_id');
    }
}
