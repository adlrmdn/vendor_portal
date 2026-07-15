<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    use HasFactory;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $primaryKey = 'id';

    protected $fillable = [
        'po_number',
        'vendor_id',
        'status',
        'total_amount',
        'currency',
        'order_date',
        'delivery_date',
        'notes',
        'reference',
    ];

    protected $casts = [
        'id' => 'string',
        'vendor_id' => 'string',
        'total_amount' => 'decimal:2',
        'order_date' => 'date',
        'delivery_date' => 'date',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->po_number)) {
                $model->po_number = 'PO-'.strtoupper(uniqid());
            }
        });
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function items()
    {
        return $this->hasMany(PoItem::class, 'po_id');
    }

    public function packingSlips()
    {
        return $this->hasMany(PackingSlip::class, 'po_id');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['pending', 'processing']);
    }

    public function updateTotalAmount()
    {
        $total = $this->items()->sum('total_price');
        $this->total_amount = $total;
        $this->save();

        return $this;
    }

    public function updateStatusBasedOnItems()
    {
        $this->loadMissing('items');

        if ($this->items->isEmpty()) {
            return $this; // Keep existing status if no items
        }

        if (
            $this->items->every(function ($item) {
                return $item->status === 'pending';
            })
        ) {
            $this->status = 'pending';
        } elseif (
            $this->items->every(function ($item) {
                return $item->status === 'completed';
            })
        ) {
            $this->status = 'completed';
        } else {
            $this->status = 'processing';
        }

        $this->save();

        return $this;
    }
}
