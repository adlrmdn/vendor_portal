<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Append-only audit trail for the PC module. Modeled exactly on
 * SubconApprovalLog — snapshot columns, not FKs, so history survives
 * deletion of the actor or the entity it refers to.
 */
class PcAuditLog extends Model
{
    protected $table = 'pc_audit_logs';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $primaryKey = 'id';

    protected $fillable = [
        'actor', 'actor_role', 'event_type', 'entity_type', 'entity_id',
        'entity_label', 'note', 'ip_address', 'user_agent', 'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
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

    /**
     * Append one audit row. Best-effort: a logging failure must never
     * block the real action that triggered it.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function record(array $attributes): void
    {
        try {
            static::create($attributes);
        } catch (\Throwable $e) {
            Log::error('PC audit log write failed: '.$e->getMessage());
        }
    }
}
