<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * An additional D365 production group manually pinned onto a subcon work
 * order — see the creating migration's docblock for why this exists.
 */
class SubconOrderExtraProductionGroup extends Model
{
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
        'subcon_order_id',
        'production_group',
        'added_by',
    ];

    protected $casts = [
        'id' => 'string',
        'subcon_order_id' => 'string',
    ];

    public function order()
    {
        return $this->belongsTo(SubconOrder::class, 'subcon_order_id');
    }
}
