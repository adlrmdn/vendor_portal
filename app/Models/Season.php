<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Admin-managed only — unlike HS Code/Lot/Transport/Delivery Term, the spec
 * does not make Season preparer-addable (PC-H04: must be is_open to be
 * selectable on a new contract).
 */
class Season extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $primaryKey = 'id';

    protected $fillable = [
        'code', 'label', 'is_open', 'sort_key', 'is_active', 'created_by',
    ];

    protected $casts = [
        'is_open' => 'boolean',
        'is_active' => 'boolean',
        'sort_key' => 'integer',
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

    public function scopeOpenForNewContracts($query)
    {
        return $query->where('is_active', true)->where('is_open', true);
    }
}
