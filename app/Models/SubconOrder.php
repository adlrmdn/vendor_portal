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
     * QMS project statuses that need no further workflow action: completed
     * (invoiced) and removed_completed (archived in the QC console after
     * already being done). Plain 'removed' is deliberately NOT included —
     * per the QC console (forge/src/components/FormView.tsx), that status
     * just means an operator archived/hid the project on their device
     * ("This will archive the style and hide it from all directories");
     * it's restorable via a re-scan and does not imply the approval
     * workflow finished. Treating it as inactive hid genuinely pending
     * Director approvals. Every pending list/badge excludes only these.
     */
    public const QMS_INACTIVE_PROJECT_STATUSES = ['completed', 'removed_completed'];

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
                ->whereNotIn('project_id', function ($q) {
                    $q->select('project_id')->from('packaging_projects')
                        ->whereIn('status', self::QMS_INACTIVE_PROJECT_STATUSES);
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
        // Official Report rows union into this same tab
        // (SubconAdminController::reportValidations()) but live in their own
        // table, not qms — counted separately so the sidebar badge doesn't
        // undercount against what the tab actually lists.
        $officialReports = (int) SubconOfficialReport::where('status', SubconOfficialReport::STATUS_PENDING_HO)->count();

        try {
            if (! Schema::connection('qms')->hasTable('packaging_project_sessions')
                || ! Schema::connection('qms')->hasColumn('packaging_project_sessions', 'ho_validation_signature')) {
                return $officialReports;
            }

            return $officialReports + (int) DB::connection('qms')->table('packaging_project_sessions')
                ->whereNotNull('approval_token')
                ->where('ho_approval_signature', 'like', 'Digitally Signed:%')
                ->where(function ($q) {
                    $q->whereNull('ho_validation_signature')->orWhere('ho_validation_signature', '');
                })
                ->where(function ($q) {
                    $q->whereNull('director_approval_signature')->orWhere('director_approval_signature', '');
                })
                ->whereNotIn('project_id', function ($q) {
                    $q->select('project_id')->from('packaging_projects')
                        ->whereIn('status', self::QMS_INACTIVE_PROJECT_STATUSES);
                })
                ->count();
        } catch (\Throwable $e) {
            return $officialReports;
        }
    }

    /**
     * QMS sessions signed by MD Production but still awaiting the Director's
     * authorization (stage 3). Same best-effort contract as
     * pendingFinalApprovalCount() — never throws.
     */
    public static function pendingDirectorApprovalCount(): int
    {
        // Same union as pendingValidateSendCount() above, for the Director
        // Approvals tab's own Official Report rows.
        $officialReports = (int) SubconOfficialReport::where('status', SubconOfficialReport::STATUS_PENDING_DIRECTOR)->count();

        try {
            if (! Schema::connection('qms')->hasTable('packaging_project_sessions')
                || ! Schema::connection('qms')->hasColumn('packaging_project_sessions', 'director_approval_signature')) {
                return $officialReports;
            }

            return $officialReports + (int) DB::connection('qms')->table('packaging_project_sessions')
                ->whereNotNull('approval_token')
                ->where('ho_approval_signature', 'like', 'Digitally Signed:%')
                // Two-step MD gate: only validated & sent sessions are actually
                // awaiting the Director (keeps the badge in sync with the tab).
                ->when(
                    Schema::connection('qms')->hasColumn('packaging_project_sessions', 'ho_validation_signature'),
                    fn ($q) => $q->whereNotNull('ho_validation_signature')->where('ho_validation_signature', '!=', '')
                )
                ->where(function ($q) {
                    $q->whereNull('director_approval_signature')->orWhere('director_approval_signature', '');
                })
                // Completed (legacy two-stage) or console-removed projects need
                // no Director action — keep them out of the badge.
                ->whereNotIn('project_id', function ($q) {
                    $q->select('project_id')->from('packaging_projects')
                        ->whereIn('status', self::QMS_INACTIVE_PROJECT_STATUSES);
                })
                ->count();
        } catch (\Throwable $e) {
            return $officialReports;
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
        'cutting_partial',
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
        'reject_gate',
        'reject_reason',
        'rejected_at',
        'distribution_id',
        'production_group',
        'sizes_count',
        'label_gen_status',
        'label_gen_error',
        'label_gen_at',
        'd365_finished_detected_at',
    ];

    protected $casts = [
        'id' => 'string',
        'vendor_id' => 'string',
        'order_date' => 'date',
        'due_date' => 'date',
        'd365_finished_detected_at' => 'datetime',
        'rejected_at' => 'datetime',
        'blister_capacity' => 'integer',
        'sack_capacity' => 'integer',
        'short_roll' => 'decimal:2',
        'sisa_kain' => 'decimal:2',
        'kepala_kain' => 'decimal:2',
        'retur_kain' => 'decimal:2',
        'cutting_partial' => 'boolean',
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
     * A known D365 business-rule rejection (closed store, empty Gramasi) —
     * distinct from LABEL_GEN_FAILED because retrying can never fix it; only
     * a person correcting the D365/report data can. See
     * GenerateSubconLabels::NON_RETRYABLE_DTT_ERROR_PATTERNS.
     */
    public const LABEL_GEN_BLOCKED = 'blocked';

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

    /** True if the last run hit a D365 business-rule rejection retrying can't fix. */
    public function labelGenBlocked(): bool
    {
        return $this->label_gen_status === self::LABEL_GEN_BLOCKED;
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

    /**
     * Record a generation failure with a human-readable reason. Also drops the
     * stage back to waiting_distribution — even a recalc-only retry failure —
     * so the order simply reappears on the Generate Labels tab instead of
     * sitting invisible at `labels` with nothing showing the problem. Skipped
     * for already-completed orders: the recalc button stays reachable there
     * too, but a failure shouldn't un-complete a finished PO.
     */
    public function markLabelGenFailed(string $reason): void
    {
        $this->forceFill([
            ...($this->workflow_stage !== self::STAGE_COMPLETED
                ? ['workflow_stage' => self::STAGE_WAITING_DISTRIBUTION]
                : []),
            'label_gen_status' => self::LABEL_GEN_FAILED,
            'label_gen_error' => \Illuminate\Support\Str::limit($reason, 1000),
            'label_gen_at' => now(),
        ])->save();
    }

    /**
     * Record a D365 business-rule rejection (closed store, empty Gramasi) —
     * same bookkeeping as markLabelGenFailed(), but a distinct status so the
     * UI can show it as "needs a D365 fix" rather than "retry me".
     */
    public function markLabelGenBlocked(string $reason): void
    {
        $this->forceFill([
            ...($this->workflow_stage !== self::STAGE_COMPLETED
                ? ['workflow_stage' => self::STAGE_WAITING_DISTRIBUTION]
                : []),
            'label_gen_status' => self::LABEL_GEN_BLOCKED,
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

    /**
     * True when this row isn't actually a CMT cutting work order — it's an
     * Add Process PO (ADD-Was/ADD-Embro/ADD-Sab/ADD-Pleat) that got ingested
     * before d365:sync-subcon-orders started excluding those at sync time,
     * or was flagged by hand. Gates every vendor-facing edit action below as
     * defense-in-depth, and callers must also exclude it from vendor
     * visibility entirely via scopeForVendor().
     */
    public function isAddProcess(): bool
    {
        return ! empty($this->add_process_operation);
    }

    /**
     * The only query vendor-facing controllers should use to scope orders to
     * their own vendor — excludes any order flagged isAddProcess() so an Add
     * Process PO (never a real cutting job) is invisible to the vendor
     * entirely, not just blocked from editing. Replaces a bare
     * where('vendor_id', ...) everywhere in SubconVendorController.
     */
    public function scopeForVendor($query, string $vendorId)
    {
        return $query->where('vendor_id', $vendorId)->whereNull('add_process_operation');
    }

    /**
     * Excludes isAddProcess() rows — use this whenever resolving "the CMT
     * work order" for a production group (or any other non-vendor-scoped
     * lookup) rather than a bare where(). A production group can legitimately
     * have both a real CMT order and an Add Process one (same group, two POs
     * — see addProcessPurchaseOrdersForGroup()'s docblock); without this, a
     * plain ->first() has no orderBy and a UUID PK, so which row comes back
     * is unspecified, not merely "probably fine" — confirmed live for
     * MPG/PRG/2607/000207 (see memory subcon-addpro-po-ingested-as-cmt-order.md).
     */
    public function scopeCmtOnly($query)
    {
        return $query->whereNull('add_process_operation');
    }

    /** Vendor may edit/submit the cutting report (qty per size). */
    public function canEditCutting(): bool
    {
        return ! $this->isAddProcess() && $this->workflow_stage === self::STAGE_CUTTING;
    }

    /** Vendor may submit a cutting revision after cutting was already approved, up until the order is completed. */
    public function canReviseCutting(): bool
    {
        return ! $this->isAddProcess() && in_array($this->workflow_stage, [
            self::STAGE_GRAMASI,
            self::STAGE_WAITING_DISTRIBUTION,
            self::STAGE_LABELS,
        ], true) && $this->cutting_approved_at !== null && ! $this->isAwaitingApproval();
    }

    /** Vendor may edit/submit gramasi + blister capacity. */
    public function canEditGramasi(): bool
    {
        return ! $this->isAddProcess() && $this->workflow_stage === self::STAGE_GRAMASI;
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

    /**
     * Vendor may attach/edit material-return declarations any time up until
     * Report Validation is actually sent to the Director — i.e. any
     * workflow stage, closing the moment ho_validation_signature is set on
     * this order's QMS session (or immediately if no session exists yet,
     * since that means Report Validation obviously hasn't happened) — AND,
     * separately, the moment MD Production presses "Send to Material Flow"
     * (a MaterialReturnTask exists at all, pending or already checked): once
     * dispatched, the vendor can never edit again, so the declared figures
     * Material Flow is validating can't shift underneath the check — only MD
     * Production (materialReturnAdminWindowOpen()) can still edit from there.
     */
    public function materialReturnVendorWindowOpen(): bool
    {
        if ($this->qmsSignatureSet('ho_validation_signature')) {
            return false;
        }

        try {
            return ! \App\Models\MaterialReturnTask::where('order_id', $this->id)->exists();
        } catch (\Throwable $e) {
            return true;
        }
    }

    /**
     * Admin (MD Production) may attach from the Final Approval stage
     * (approval_token set) through Report Validation (before
     * ho_validation_signature is set) — the two admin stages MD Production
     * itself owns, per the "final approval or report validation" instruction.
     */
    public function materialReturnAdminWindowOpen(): bool
    {
        return $this->qmsTokenSet('approval_token') && ! $this->qmsSignatureSet('ho_validation_signature');
    }

    /**
     * Best-effort read of one column on this order's active QMS session
     * (joined by production_group, same key QcApprovalController::subconContext()
     * resolves projects through). Never throws — an unreachable/older QMS
     * schema is treated as "not set" so the material-return windows degrade
     * open for vendor / closed for admin rather than 500ing.
     */
    private function qmsColumnQuery(string $column)
    {
        if (empty($this->production_group)) {
            return null;
        }

        if (! Schema::connection('qms')->hasTable('packaging_project_sessions')
            || ! Schema::connection('qms')->hasColumn('packaging_project_sessions', $column)) {
            return null;
        }

        return DB::connection('qms')->table('packaging_project_sessions')
            ->join('packaging_projects', 'packaging_projects.project_id', '=', 'packaging_project_sessions.project_id')
            ->where('packaging_projects.production_group', $this->production_group);
    }

    /** Text/signature columns (ho_approval_signature, ho_validation_signature, ...): null or '' both mean "unset". */
    private function qmsSignatureSet(string $column): bool
    {
        try {
            $query = $this->qmsColumnQuery($column);

            return $query
                ? $query->where(function ($q) use ($column) {
                    $q->whereNotNull($column)->where($column, '!=', '');
                })->exists()
                : false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** UUID-typed columns (approval_token): only null means "unset" — comparing a uuid column to '' errors in Postgres. */
    private function qmsTokenSet(string $column): bool
    {
        try {
            $query = $this->qmsColumnQuery($column);

            return $query ? $query->whereNotNull($column)->exists() : false;
        } catch (\Throwable $e) {
            return false;
        }
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

    /**
     * Add Process reports aren't owned by a SubconOrder (in-house production
     * groups have ADD-* steps with no subcon order at all) — matched by
     * production_group string instead of a real foreign key.
     */
    public function addProcessReports()
    {
        return $this->hasMany(SubconAddProcessReport::class, 'production_group', 'production_group');
    }

    public function jobLogs()
    {
        return $this->hasMany(SubconJobLog::class, 'order_id');
    }

    public function extraProductionGroups()
    {
        return $this->hasMany(SubconOrderExtraProductionGroup::class, 'subcon_order_id');
    }
}
