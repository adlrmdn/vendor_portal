<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $keyType = 'string';
    public $incrementing = false;
    protected $primaryKey = 'id';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'vendor_id'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'id' => 'string',
        'email_verified_at' => 'datetime',
        'vendor_id' => 'string'
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    public function isFabricAdmin()
    {
        return $this->role === 'fabric_admin';
    }

    public function isFabricVendor()
    {
        return $this->role === 'fabric_vendor';
    }

    public function isSubconAdmin()
    {
        return $this->role === 'subcon_admin';
    }

    public function isSubconVendor()
    {
        return $this->role === 'subcon_vendor';
    }

    public function isFabricSide()
    {
        return in_array($this->role, ['admin', 'fabric_admin', 'fabric_vendor']);
    }

    public function isSubconSide()
    {
        return in_array($this->role, ['admin', 'subcon_admin', 'subcon_vendor']);
    }

    /** @deprecated use isFabricVendor() */
    public function isVendor()
    {
        return $this->role === 'fabric_vendor';
    }

    public function scopeVendorUsers($query, $vendorId)
    {
        return $query->where('vendor_id', $vendorId)->where('role', 'fabric_vendor');
    }
}