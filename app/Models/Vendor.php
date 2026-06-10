<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;
    protected $primaryKey = 'id';

    protected $fillable = [
        'name',
        'vendor_code',
        'group',
        'type',
        'contact_info',
        'is_active'
    ];

    protected $casts = [
        'id' => 'string',
        'contact_info' => 'array',
        'is_active' => 'boolean'
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function activePurchaseOrders()
    {
        return $this->purchaseOrders()->whereIn('status', ['pending', 'processing']);
    }

    public function subconOrders()
    {
        return $this->hasMany(SubconOrder::class, 'vendor_id');
    }

    public function isFabric()
    {
        return $this->type === 'fabric';
    }

    public function isSubcon()
    {
        return $this->type === 'subcon';
    }
}