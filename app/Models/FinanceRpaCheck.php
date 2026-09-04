<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class FinanceRpaCheck extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['rpa_queue_id', 'rpa_type', 'checked_by', 'checked_at'];

    protected $casts = [
        'checked_at' => 'datetime',
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

    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }
}
