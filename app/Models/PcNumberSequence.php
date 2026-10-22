<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Plain model, no business logic — the lock/increment logic lives in
 * PcContractNumberService, which locks this row with lockForUpdate()
 * inside a transaction (see that service for the exact shape).
 */
class PcNumberSequence extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $primaryKey = 'id';

    protected $fillable = ['doc_type', 'year_yy', 'last_number'];

    protected $casts = [
        'last_number' => 'integer',
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
}
