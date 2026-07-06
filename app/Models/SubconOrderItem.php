<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SubconOrderItem extends Model
{
    use HasFactory;

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
        'order_id',
        'item_number',
        'description',
        'quantity',
        'unit',
        'status',
        'notes',
    ];

    protected $casts = [
        'id' => 'string',
        'order_id' => 'string',
        'quantity' => 'decimal:2',
    ];

    public function order()
    {
        return $this->belongsTo(SubconOrder::class, 'order_id');
    }
}
