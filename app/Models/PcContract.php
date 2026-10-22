<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * The PC contract header. Status machine and freeze mechanics are
 * documented in the PC Phase 2 plan. Deliberately carries no transition
 * methods with side effects (audit log, mail, number issuance, PDF) —
 * those live in PcContractApprovalService/PcContractController so they
 * stay testable and TOTP-gated at the right layer. Only pure state
 * predicates live here, matching SubconOrder::canEditCutting()'s idiom.
 */
class PcContract extends Model
{
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
        return $this->status === 'draft';
    }

    public function isAwaitingVendor(): bool
    {
        return $this->status === 'pending_vendor_review';
    }

    public function isAwaitingApproval(): bool
    {
        return $this->status === 'pending_final_approval';
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function isLive(): bool
    {
        return ! in_array($this->status, ['cancelled', 'superseded'], true);
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
