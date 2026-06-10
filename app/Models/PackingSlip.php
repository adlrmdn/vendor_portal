<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackingSlip extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;
    protected $primaryKey = 'id';

    protected $fillable = [
        'slip_number',
        'po_id',
        'vendor_id',
        'items',
        'printed_count',
        'last_printed_at',
        'pdf_path',
        'delivery_note'
    ];

    protected $casts = [
        'id' => 'string',
        'po_id' => 'string',
        'vendor_id' => 'string',
        'items' => 'array',
        'last_printed_at' => 'datetime'
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) \Illuminate\Support\Str::uuid();
            }
            if (empty($model->slip_number)) {
                $model->slip_number = 'SLIP-' . date('Ymd') . '-' . strtoupper(uniqid());
            }
        });
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_id');
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function markPrinted()
    {
        $this->printed_count++;
        $this->last_printed_at = now();
        $this->save();
        return $this;
    }
}