<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
     * Count of QC-console packaging sessions that passed stage-1 confirmation but
     * still await Final (HO) sign-off — the "Final Approval" rows surfaced in the
     * subcon Approvals tab. Best-effort read from the QMS DB, fully guarded so it
     * never breaks the admin nav badge (rendered on every page) or the dashboard
     * if QMS is unreachable or the schema is older.
     */
    public static function pendingFinalApprovalCount(): int
    {
        try {
            if (! Schema::connection('qms')->hasTable('packaging_project_sessions')
                || ! Schema::connection('qms')->hasColumn('packaging_project_sessions', 'approval_status')) {
                return 0;
            }

            return (int) DB::connection('qms')->table('packaging_project_sessions')
                ->whereNotNull('approval_token')
                ->where('approval_status', 'approved')
                ->where(function ($q) {
                    $q->whereNull('ho_approval_signature')->orWhere('ho_approval_signature', '');
                })
                ->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * QMS sessions approved by MD Production (step 1) but still awaiting the
     * "Validate & Send Approval" step — the Report Validation tab badge. Same
     * best-effort contract as pendingFinalApprovalCount() — never throws.
     */
    public static function pendingValidateSendCount(): int
    {
        try {
            if (! Schema::connection('qms')->hasTable('packaging_project_sessions')
                || ! Schema::connection('qms')->hasColumn('packaging_project_sessions', 'ho_validation_signature')) {
                return 0;
            }

            return (int) DB::connection('qms')->table('packaging_project_sessions')
                ->whereNotNull('approval_token')
                ->where('ho_approval_signature', 'like', 'Digitally Signed:%')
                ->where(function ($q) {
                    $q->whereNull('ho_validation_signature')->orWhere('ho_validation_signature', '');
                })
                ->where(function ($q) {
                    $q->whereNull('director_approval_signature')->orWhere('director_approval_signature', '');
                })
                ->whereNotIn('project_id', function ($q) {
                    $q->select('project_id')->from('packaging_projects')->where('status', 'completed');
                })
                ->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * QMS sessions signed by MD Production but still awaiting the Director's
     * authorization (stage 3). Same best-effort contract as
     * pendingFinalApprovalCount() — never throws.
     */
    public static function pendingDirectorApprovalCount(): int
    {
        try {
            if (! Schema::connection('qms')->hasTable('packaging_project_sessions')
                || ! Schema::connection('qms')->hasColumn('packaging_project_sessions', 'director_approval_signature')) {
                return 0;
            }

            return (int) DB::connection('qms')->table('packaging_project_sessions')
                ->whereNotNull('approval_token')
                ->where('ho_approval_signature', 'like', 'Digitally Signed:%')
                ->where(function ($q) {
                    $q->whereNull('director_approval_signature')->orWhere('director_approval_signature', '');
                })
                // Legacy projects completed under the old two-stage flow need
                // no Director action — keep them out of the badge.
                ->whereNotIn('project_id', function ($q) {
                    $q->select('project_id')->from('packaging_projects')->where('status', 'completed');
                })
                ->count();
        } catch (\Throwable $e) {
            return 0;
        }
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
        'remarks',
        'distribution_id',
        'production_group',
        'sizes_count',
        'label_gen_status',
        'label_gen_error',
        'label_gen_at',
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
        'label_gen_at' => 'datetime',
    ];

    // --- Label-generation state (async DTT/RPA run) -----------------------
    public const LABEL_GEN_GENERATING = 'generating';

    public const LABEL_GEN_FAILED = 'failed';

    /**
     * A generation run is considered live only for this long. Guards against a
     * crashed/killed job leaving the button locked forever — comfortably above
     * the job's own timeout×tries so a genuinely-running job is never unlocked
     * out from under itself.
     */
    public const LABEL_GEN_STALE_MINUTES = 12;

    /** True while a label-generation job is genuinely in flight (not stale). */
    public function isGeneratingLabels(): bool
    {
        return $this->label_gen_status === self::LABEL_GEN_GENERATING
            && $this->label_gen_at
            && $this->label_gen_at->gt(now()->subMinutes(self::LABEL_GEN_STALE_MINUTES));
    }

    /** True if the last label-generation run failed (and none is in flight). */
    public function labelGenFailed(): bool
    {
        return $this->label_gen_status === self::LABEL_GEN_FAILED;
    }

    /** Mark a generation run as started (button-locking state). */
    public function markLabelGenStarted(): void
    {
        $this->forceFill([
            'label_gen_status' => self::LABEL_GEN_GENERATING,
            'label_gen_error' => null,
            'label_gen_at' => now(),
        ])->save();
    }

    /** Record a generation failure with a human-readable reason. */
    public function markLabelGenFailed(string $reason): void
    {
        $this->forceFill([
            'label_gen_status' => self::LABEL_GEN_FAILED,
            'label_gen_error' => \Illuminate\Support\Str::limit($reason, 1000),
            'label_gen_at' => now(),
        ])->save();
    }

    /** Clear generation state after a successful run. */
    public function clearLabelGenState(): void
    {
        $this->forceFill([
            'label_gen_status' => null,
            'label_gen_error' => null,
        ])->save();
    }

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

    public function jobLogs()
    {
        return $this->hasMany(SubconJobLog::class, 'order_id');
    }
}
