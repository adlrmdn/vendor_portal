<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * One row per "send to Material Flow" dispatch — created when MD Production
 * asks value_stream_ops's inventory staff to check material the SUBCON
 * VENDOR returned to us (e.g. unused/excess cut fabric). This is NOT about
 * material we return to our own upstream fabric supplier — a different,
 * unbuilt flow; don't repurpose this table for that. Lives in the `wms`
 * database (see config/database.php); the status column is flipped to
 * 'checked' by value_stream_ops, never by this app —
 * QcApprovalController::hoSendApproval only reads it.
 */
class MaterialReturnTask extends Model
{
    protected $connection = 'wms';

    protected $table = 'material_return_tasks';

    protected $keyType = 'string';

    public $incrementing = false;

    public const STATUS_PENDING = 'pending';

    public const STATUS_CHECKED = 'checked';

    protected $fillable = [
        'order_id', 'order_number', 'vendor_name', 'production_group',
        'status', 'requested_by', 'requested_at',
        'checked_by', 'checked_at', 'checked_note',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'checked_at' => 'datetime',
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

    public function isChecked(): bool
    {
        return $this->status === self::STATUS_CHECKED;
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MaterialReturnAttachment::class, 'task_id');
    }
}
