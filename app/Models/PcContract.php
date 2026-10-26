<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * The PC contract header.
 * Canonical status machine:
 * draft -> pending_approval -> approved -> sent_to_supplier -> confirmed
 * Additional paths:
 *   pending_approval -> draft (rejected by approver with reason)
 *   sent_to_supplier -> supplier_queried (vendor query with reason)
 *   revisions: approved/sent_to_supplier/supplier_queried/confirmed -> superseded (when new rev confirmed)
 *   cancellation: any live status -> cancelled (staff-only with reason)
 */
class PcContract extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_SENT_TO_SUPPLIER = 'sent_to_supplier';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_SUPPLIER_QUERIED = 'supplier_queried';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_SUPERSEDED = 'superseded';
    public const STATUS_CANCELLED = 'cancelled';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $primaryKey = 'id';

    protected $fillable = [
        'contract_number', 'revision_no', 'previous_revision_id', 'status',
        'vendor_id', 'fabric_type', 'currency_id', 'payment_term_id',
        'delivery_term_id', 'transport_mode_id', 'vat_treatment_id', 'vat_rate',
        'season_id', 'order_type_id', 'contract_date', 'created_by',
        'submitted_at', 'submitted_by',
        'vendor_decision', 'vendor_decision_at', 'vendor_decision_by', 'vendor_decision_reason',
        'approval_decision', 'approval_decision_at', 'approval_decision_by', 'approval_decision_reason',
        'confirmed_at', 'confirmed_by',
        'cancelled_at', 'cancelled_by', 'cancel_reason',
        'header_snapshot', 'pdf_generated_at', 'internal_remarks',
    ];

    protected $casts = [
        'revision_no' => 'integer',
        'vat_rate' => 'decimal:2',
        'contract_date' => 'date',
        'submitted_at' => 'datetime',
        'vendor_decision_at' => 'datetime',
        'approval_decision_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'pdf_generated_at' => 'datetime',
        'header_snapshot' => 'array',
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

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function paymentTerm()
    {
        return $this->belongsTo(PaymentTerm::class);
    }

    public function deliveryTerm()
    {
        return $this->belongsTo(DeliveryTerm::class);
    }

    public function transportMode()
    {
        return $this->belongsTo(TransportMode::class);
    }

    public function vatTreatment()
    {
        return $this->belongsTo(VatTreatment::class);
    }

    public function season()
    {
        return $this->belongsTo(Season::class);
    }

    public function orderType()
    {
        return $this->belongsTo(OrderType::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approval_decision_by');
    }

    public function previousRevision()
    {
        return $this->belongsTo(self::class, 'previous_revision_id');
    }

    public function nextRevision()
    {
        return $this->hasOne(self::class, 'previous_revision_id');
    }

    public function lines()
    {
        return $this->hasMany(PcContractLine::class, 'contract_id')->orderBy('line_no');
    }

    public function deliveries()
    {
        return $this->hasMany(PcContractDelivery::class, 'contract_id');
    }

    public function notes()
    {
        return $this->hasMany(PcContractNote::class, 'contract_id')->orderBy('clause_number');
    }

    public function tolerances()
    {
        return $this->hasMany(PcContractTolerance::class, 'contract_id')->orderBy('sort_order');
    }

    public function extraNotes()
    {
        return $this->hasMany(PcContractExtraNote::class, 'contract_id');
    }

    public function vendorResponses()
    {
        return $this->hasMany(PcContractVendorResponse::class, 'contract_id');
    }

    public function files()
    {
        return $this->hasMany(PcContractFile::class, 'contract_id');
    }

    public function scopeForVendor($query, string $vendorId)
    {
        return $query->where('vendor_id', $vendorId);
    }

    public function scopeStatusIs($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeSearch($query, string $term)
    {
        $like = '%'.mb_strtolower($term).'%';

        return $query->where(function ($q) use ($like) {
            $q->orWhereRaw('LOWER(contract_number) LIKE ?', [$like])
                ->orWhereHas('vendor', function ($vq) use ($like) {
                    $vq->whereRaw('LOWER(name) LIKE ?', [$like]);
                });
        });
    }

    public function isEditableByPreparer(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isAwaitingApproval(): bool
    {
        return $this->status === self::STATUS_PENDING_APPROVAL;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isSentToSupplier(): bool
    {
        return $this->status === self::STATUS_SENT_TO_SUPPLIER;
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function isQueried(): bool
    {
        return $this->status === self::STATUS_SUPPLIER_QUERIED;
    }

    public function isLive(): bool
    {
        return ! in_array($this->status, [self::STATUS_CANCELLED, self::STATUS_SUPERSEDED], true);
    }

    public function canBeRevised(): bool
    {
        return in_array($this->status, [
            self::STATUS_APPROVED,
            self::STATUS_SENT_TO_SUPPLIER,
            self::STATUS_SUPPLIER_QUERIED,
            self::STATUS_CONFIRMED,
        ], true);
    }

    public function canBeCancelledBy(User $user): bool
    {
        if (! $this->isLive()) {
            return false;
        }

        return $user->isAdmin()
            || $user->id === $this->created_by
            || \App\Http\Controllers\PcAdminController::isPcApproverUser($user);
    }
}
