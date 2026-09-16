<?php

namespace App\Models\Vsm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * App-owned table physically hosted on the `vsm` connection — see
 * App\Services\SubconDailyProductionService for why. Not part of the
 * external PLM/D365-sync schema the rest of `vsm` belongs to.
 */
class SubconDailyProductionEntry extends Model
{
    use HasFactory;

    protected $connection = 'vsm';

    protected $table = 'subcon_daily_production_entries';

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
    }

    // The physical production steps, in order. STAGE_LOAD_SEWING/
    // STAGE_LOAD_FINISHING are no longer entered by the vendor or rendered
    // (SubconDailyProductionService::forOrder() computes them instead, as
    // a mirror of the stage right before them — everything cut eventually
    // gets loaded to sewing, everything sewn eventually gets loaded to
    // finishing) — kept here, not deleted, so existing rows/the stage enum
    // stay intact. STAGE_FINISHING_PACKING is likewise kept for any old
    // rows already saved under it, but is superseded going forward by the
    // 3-step breakdown (STAGE_FINISHING/STAGE_QC/STAGE_PACKING).
    public const STAGE_CUTTING = 'cutting';

    public const STAGE_LOAD_SEWING = 'load_sewing';

    public const STAGE_SEWING = 'sewing';

    public const STAGE_LOAD_FINISHING = 'load_finishing';

    public const STAGE_FINISHING_PACKING = 'finishing_packing';

    public const STAGE_FINISHING = 'finishing';

    public const STAGE_QC = 'qc';

    public const STAGE_PACKING = 'packing';

    /** Used when a stage has no ratio concept (everything but cutting) — a real
     *  value, not null, so the unique index still dedupes upserts correctly. */
    public const NO_RATIO = '';

    protected $fillable = [
        'order_id',
        'order_number',
        'vendor_name',
        'stage',
        'ratio_label',
        'prod_id',
        'size',
        'entry_date',
        'quantity',
        'notes',
        'entered_by_user_id',
        'entered_by_name',
    ];

    protected $casts = [
        'id' => 'string',
        'order_id' => 'string',
        'entered_by_user_id' => 'string',
        'entry_date' => 'date',
        'quantity' => 'integer',
    ];

    public static function stages(): array
    {
        return [
            self::STAGE_CUTTING => 'Cutting',
            self::STAGE_LOAD_SEWING => 'Load to Sewing',
            self::STAGE_SEWING => 'Sewing',
            self::STAGE_LOAD_FINISHING => 'Load to Finishing',
            self::STAGE_FINISHING_PACKING => 'Finishing & Packing',
            self::STAGE_FINISHING => 'Finishing',
            self::STAGE_QC => 'QC',
            self::STAGE_PACKING => 'Packing',
        ];
    }
}
