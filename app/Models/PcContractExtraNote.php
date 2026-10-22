<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PcContractExtraNote extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $primaryKey = 'id';

    protected $fillable = [
        'contract_id', 'author_id', 'author_name_snapshot', 'author_role_snapshot', 'body', 'is_internal',
    ];

    protected $casts = [
        'is_internal' => 'boolean',
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

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function scopeVisibleToVendor($query)
    {
        return $query->where('is_internal', false);
    }
}
