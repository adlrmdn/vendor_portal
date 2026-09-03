<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One row per uploaded delivery-note file for material the subcon vendor
 * returns to us (see MaterialReturnTask's docblock — not material we return
 * to our own fabric supplier). Lives in the `wms` database. Can exist before
 * any MaterialReturnTask is dispatched — MaterialReturnService::dispatchTask()
 * backfills task_id onto any unlinked rows for the order at dispatch time.
 */
class MaterialReturnAttachment extends Model
{
    protected $connection = 'wms';

    protected $table = 'material_return_attachments';

    protected $keyType = 'string';

    public $incrementing = false;

    public const ROLE_VENDOR = 'vendor';

    public const ROLE_ADMIN = 'admin';

    protected $fillable = [
        'order_id', 'order_number', 'vendor_name', 'task_id',
        'uploaded_by_role', 'uploaded_by_name',
        's3_disk', 's3_path', 'original_filename', 'mime_type', 'size_bytes',
        'note', 'uploaded_at',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
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

    public function task(): BelongsTo
    {
        return $this->belongsTo(MaterialReturnTask::class, 'task_id');
    }
}
