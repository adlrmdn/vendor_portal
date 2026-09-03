<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One row per (order, item) declared return quantity — fabric or accessory
 * (see MaterialReturnTask's docblock: this is material the subcon vendor
 * returns to us, not our own returns to a fabric supplier). Upserted by
 * MaterialReturnService::saveLines(), not append-only. `qty_actual` is
 * value_stream_ops's "corrective measure" — the real count at check time,
 * which may differ from `qty_declared`. Lives in the `wms` database.
 */
class MaterialReturnLine extends Model
{
    protected $connection = 'wms';

    protected $table = 'material_return_lines';

    protected $keyType = 'string';

    public $incrementing = false;

    public const TYPE_FABRIC = 'fabric';

    public const TYPE_ACCESSORY = 'accessory';

    protected $fillable = [
        'order_id', 'order_number', 'vendor_name', 'task_id',
        'item_type', 'label', 'item_number', 'unit',
        'qty_declared', 'qty_actual',
        'uploaded_by_role', 'uploaded_by_name',
    ];

    protected $casts = [
        'qty_declared' => 'float',
        'qty_actual' => 'float',
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

    public function variance(): ?float
    {
        return $this->qty_actual === null ? null : round($this->qty_actual - $this->qty_declared, 2);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(MaterialReturnTask::class, 'task_id');
    }
}
