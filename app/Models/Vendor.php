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
        'is_active',
        // PC-specific enrichment fields (see
        // 2026_10_22_000022_add_pc_fields_to_vendors_table.php) — populated
        // by PcEnrichVendors for fabric vendors, used by the PC module.
        'country',
        'contact_person',
        'currency_code',
        'd365_payment_code',
        'payment_term_id',
        'supplier_group_code',
        'is_favourite',
        'synced_at',
    ];

    protected $casts = [
        'id' => 'string',
        'contact_info' => 'array',
        'is_active' => 'boolean',
        'is_favourite' => 'boolean',
        'synced_at' => 'datetime',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function paymentTerm()
    {
        return $this->belongsTo(PaymentTerm::class, 'payment_term_id');
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

    public function pcContracts()
    {
        return $this->hasMany(PcContract::class, 'vendor_id');
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
