<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Append-only — see pc_contract_vendor_responses migration docblock. */
class PcContractVendorResponse extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $primaryKey = 'id';

    protected $fillable = [
        'contract_id', 'vendor_id', 'decision', 'reason', 'responded_by', 'responded_by_name_snapshot',
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

    public function contract()
    {
        return $this->belongsTo(PcContract::class, 'contract_id');
    }

    public function respondedBy()
    {
        return $this->belongsTo(User::class, 'responded_by');
    }
}
