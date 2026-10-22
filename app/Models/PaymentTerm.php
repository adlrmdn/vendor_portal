<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Decoded from the D365 payment-term code carried on the vendor record
 * (e.g. N90 -> "90 DAYS AFTER ETD"). A contract's term is always resolved
 * automatically from Vendor.d365_payment_code (PC-H06), never typed.
 */
class PaymentTerm extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $primaryKey = 'id';

    protected $fillable = [
        'code', 'd365_code', 'label', 'method', 'days', 'basis', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'days' => 'integer',
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

    public function vendors()
    {
        return $this->hasMany(Vendor::class, 'payment_term_id');
    }
}
