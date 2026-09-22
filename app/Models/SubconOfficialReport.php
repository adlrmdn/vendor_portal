<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SubconOfficialReport extends Model
{
    use HasFactory;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $primaryKey = 'id';

    public const STATUS_PENDING_HO = 'pending_ho';

    public const STATUS_PENDING_DIRECTOR = 'pending_director';

    public const STATUS_COMPLETED = 'completed';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    protected $fillable = [
        'production_group',
        'operation',
        'status',
        'revision_count',
        'matched_po_number',
        'matched_po_vendor_code',
        'matched_po_vendor_name',
        'order_qty',
        'cut_qty',
        'good_qty',
        'reject_qty',
        'sales_price',
        'sales_price_source',
        'allowed_limit',
        'exceeding_reject_qty',
        'penalty_price',
        'reject_penalty',
        'additional_deduction',
        'remarks',
        'ho_approval_signature',
        'ho_approved_at',
        'director_approval_signature',
        'director_approved_at',
        'director_decline_reason',
    ];

    protected $casts = [
        'id' => 'string',
        'revision_count' => 'integer',
        'order_qty' => 'integer',
        'cut_qty' => 'integer',
        'good_qty' => 'integer',
        'reject_qty' => 'integer',
        'allowed_limit' => 'integer',
        'exceeding_reject_qty' => 'integer',
        'sales_price' => 'float',
        'penalty_price' => 'float',
        'reject_penalty' => 'float',
        'additional_deduction' => 'float',
        'ho_approved_at' => 'datetime',
        'director_approved_at' => 'datetime',
    ];

    /** reject_penalty + additional_deduction — the figure RPA queueing and the PDF both use. */
    public function totalDeduction(): float
    {
        return (float) $this->reject_penalty + (float) $this->additional_deduction;
    }

    public function isPendingHo(): bool
    {
        return $this->status === self::STATUS_PENDING_HO;
    }

    public function isPendingDirector(): bool
    {
        return $this->status === self::STATUS_PENDING_DIRECTOR;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /** A pending_ho row that's actually a post-decline resend, not a first send. */
    public function needsRevision(): bool
    {
        return $this->isPendingHo() && $this->revision_count > 0;
    }
}
