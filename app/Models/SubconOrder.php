<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SubconOrder extends Model
{
    use HasFactory;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $primaryKey = 'id';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });

        // Status is derived from the workflow stage so it can never drift out of
        // sync with where the order actually is. 'cancelled' is the one manual,
        // terminal state we leave alone (admins toggle it explicitly).
        static::saving(function ($model) {
            if ($model->status === 'cancelled') {
                return;
            }
            $model->status = self::inferStatusFromStage($model->workflow_stage);
        });
    }

    /**
     * Map a workflow stage to the coarse order status shown across the app.
     * pending  = nothing submitted yet (cutting entry)
     * in_progress = somewhere in the submit/approve/label pipeline
     * completed = done
     */
    public static function inferStatusFromStage(?string $stage): string
    {
        return match ($stage) {
            self::STAGE_COMPLETED => 'completed',
            self::STAGE_CUTTING, null, '' => 'pending',
            default => 'in_progress',
        };
    }

    // Staged approval workflow.
    public const STAGE_CUTTING = 'cutting';                 // vendor entering cutting qty

    public const STAGE_CUTTING_REVIEW = 'cutting_review';   // awaiting cutting approval

    public const STAGE_GRAMASI = 'gramasi';                 // vendor entering gramasi + blister

    public const STAGE_GRAMASI_REVIEW = 'gramasi_review';   // awaiting gramasi approval

    public const STAGE_WAITING_DISTRIBUTION = 'waiting_distribution'; // awaiting label generation

    public const STAGE_LABELS = 'labels';                   // approved; label printing unlocked

    public const STAGE_COMPLETED = 'completed';             // PO completed

    protected $fillable = [
        'order_number',
        'vendor_id',
        'status',
        'workflow_stage',
        'blister_capacity',
        'sack_capacity',
        'short_roll',
        'sisa_kain',
        'kepala_kain',
        'retur_kain',
        'cutting_approved_at',
        'cutting_approved_by',
        'gramasi_approved_at',
        'gramasi_approved_by',
        'job_trans_status',
        'title',
        'description',
        'order_date',
        'due_date',
        'notes',
        'distribution_id',
        'production_group',
        'sizes_count',
    ];

    protected $casts = [
        'id' => 'string',
        'vendor_id' => 'string',
        'order_date' => 'date',
        'due_date' => 'date',
        'blister_capacity' => 'integer',
        'sack_capacity' => 'integer',
        'short_roll' => 'decimal:2',
        'sisa_kain' => 'decimal:2',
        'kepala_kain' => 'decimal:2',
        'retur_kain' => 'decimal:2',
        'cutting_approved_at' => 'datetime',
        'gramasi_approved_at' => 'datetime',
        'job_trans_status' => 'string',
        'distribution_id' => 'string',
        'sizes_count' => 'integer',
    ];

    /** Vendor may edit/submit the cutting report (qty per size). */
    public function canEditCutting(): bool
    {
        return $this->workflow_stage === self::STAGE_CUTTING;
    }

    /** Vendor may edit/submit gramasi + blister capacity. */
    public function canEditGramasi(): bool
    {
        return $this->workflow_stage === self::STAGE_GRAMASI;
    }

    /** An approval (cutting or gramasi) is currently pending. */
    public function isAwaitingApproval(): bool
    {
        return in_array($this->workflow_stage, [self::STAGE_CUTTING_REVIEW, self::STAGE_GRAMASI_REVIEW], true);
    }

    /** Label printing is unlocked. */
    public function canPrintLabels(): bool
    {
        return in_array($this->workflow_stage, [self::STAGE_LABELS, self::STAGE_COMPLETED], true);
    }

    /** Vendor may mark the PO complete (labels stage reached, not yet completed). */
    public function canComplete(): bool
    {
        return $this->workflow_stage === self::STAGE_LABELS;
    }

    /** Human-readable label for the current stage. */
    /** Ordered workflow stages with human labels — for filters and display. */
    public static function workflowStages(): array
    {
        return [
            self::STAGE_CUTTING => 'Cutting report entry',
            self::STAGE_CUTTING_REVIEW => 'Cutting report — awaiting approval',
            self::STAGE_GRAMASI => 'Gramasi & blister entry',
            self::STAGE_GRAMASI_REVIEW => 'Gramasi & blister — awaiting approval',
            self::STAGE_WAITING_DISTRIBUTION => 'Waiting for distribution details',
            self::STAGE_LABELS => 'Approved — ready for label printing',
            self::STAGE_COMPLETED => 'Completed',
        ];
    }

    public function stageLabel(): string
    {
        return self::workflowStages()[$this->workflow_stage]
            ?? ucfirst((string) $this->workflow_stage);
    }

    /**
     * The 6-step vendor workflow as a render-ready array. Each step carries
     * whether it is done, the currently-active one, and a short label.
     *
     * @return array<int, array{key:string,label:string,done:bool,active:bool}>
     */
    public function progressSteps(): array
    {
        $stage = $this->workflow_stage;
        $past = fn (array $stages) => in_array($stage, $stages, true);

        $activeKey = match ($stage) {
            self::STAGE_CUTTING => 'cutting',
            self::STAGE_CUTTING_REVIEW => 'capprove',
            self::STAGE_GRAMASI => 'gramasi',
            self::STAGE_GRAMASI_REVIEW => 'gapprove',
            self::STAGE_WAITING_DISTRIBUTION => 'waiting_dist',
            default => 'labels',
        };

        $steps = [
            ['key' => 'cutting',      'label' => 'Cutting Report',   'done' => $past(['gramasi', 'gramasi_review', 'waiting_distribution', 'labels', 'completed'])],
            ['key' => 'capprove',     'label' => 'Cutting Approval', 'done' => $past(['gramasi', 'gramasi_review', 'waiting_distribution', 'labels', 'completed'])],
            ['key' => 'gramasi',      'label' => 'Gramasi & Blister', 'done' => $past(['waiting_distribution', 'labels', 'completed'])],
            ['key' => 'gapprove',     'label' => 'Gramasi Approval', 'done' => $past(['waiting_distribution', 'labels', 'completed'])],
            ['key' => 'waiting_dist', 'label' => 'Waiting Distribution', 'done' => $past(['labels', 'completed'])],
            ['key' => 'labels',       'label' => 'Print & Complete', 'done' => $stage === self::STAGE_COMPLETED],
        ];

        return array_map(fn ($s) => $s + ['active' => $s['key'] === $activeKey], $steps);
    }

    /** Zero-based index of the active step (for progress-bar width, etc.). */
    public function progressIndex(): int
    {
        foreach ($this->progressSteps() as $i => $s) {
            if ($s['active']) {
                return $i;
            }
        }

        return 0;
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function items()
    {
        return $this->hasMany(SubconOrderItem::class, 'order_id');
    }

    public function cuttingReports()
    {
        return $this->hasMany(SubconCuttingReport::class, 'order_id');
    }
}
