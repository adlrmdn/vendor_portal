<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ToleranceAmendmentRequest extends Model
{
    use \Illuminate\Database\Eloquent\Concerns\HasUuids;

    protected $fillable = [
        'po_item_id',
        'type',
        'requested_qty',
        'old_underdelivery',
        'old_overdelivery',
        'new_underdelivery',
        'new_overdelivery',
        'reason',
        'approver_badge',
        'status',
        'actioned_at'
    ];

    protected $casts = [
        'requested_qty' => 'decimal:2',
        'old_underdelivery' => 'decimal:2',
        'old_overdelivery' => 'decimal:2',
        'new_underdelivery' => 'decimal:2',
        'new_overdelivery' => 'decimal:2',
        'actioned_at' => 'datetime'
    ];

    public function poItem()
    {
        return $this->belongsTo(PoItem::class, 'po_item_id');
    }
}
