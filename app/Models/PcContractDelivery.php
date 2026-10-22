<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PcContractDelivery extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $primaryKey = 'id';

    protected $fillable = [
        'contract_id', 'contract_line_id', 'lot_id', 'delivery_qty', 'eta_date', 'remarks',
    ];

    protected $casts = [
        'delivery_qty' => 'decimal:3',
        'eta_date' => 'date',
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

    public function line()
    {
        return $this->belongsTo(PcContractLine::class, 'contract_line_id');
    }

    public function lot()
    {
        return $this->belongsTo(Lot::class);
    }
}
