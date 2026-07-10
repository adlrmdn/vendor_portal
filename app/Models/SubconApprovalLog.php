<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One immutable audit row per subcon cutting/gramasi approval decision.
 * See SubconApprovalController::logDecision() for the write path.
 */
class SubconApprovalLog extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $primaryKey = 'id';

    protected $fillable = [
        'order_id', 'order_number', 'vendor_name',
        'gate', 'decision', 'actor', 'source', 'note',
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

    public function order(): BelongsTo
    {
        return $this->belongsTo(SubconOrder::class, 'order_id');
    }

    /**
     * Append one audit row. Best-effort: a logging failure must never break an
     * approval/rejection that already committed. Shared by every gate.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function record(array $attributes): void
    {
        try {
            static::create($attributes);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Subcon approval log write failed: '.$e->getMessage());
        }
    }
}
