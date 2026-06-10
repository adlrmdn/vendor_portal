<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SubconOrder extends Model
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
        'order_number',
        'vendor_id',
        'status',
        'title',
        'description',
        'order_date',
        'due_date',
        'notes',
    ];

    protected $casts = [
        'id' => 'string',
        'vendor_id' => 'string',
        'order_date' => 'date',
        'due_date' => 'date',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function items()
    {
        return $this->hasMany(SubconOrderItem::class, 'order_id');
    }
}
