<?php

namespace App\Models\Vsm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * App-owned table physically hosted on the `vsm` connection — see
 * App\Services\SubconDailyProductionService for why. Not part of the
 * external PLM/D365-sync schema the rest of `vsm` belongs to.
 *
 * Stores optional overall per-day notes logged during daily production tracking,
 * both per-style (order_id populated) and general per-day (order_id null).
 */
class SubconDailyProductionDayNote extends Model
{
    use HasFactory;

    protected $connection = 'vsm';

    protected $table = 'subcon_daily_production_day_notes';

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

    protected $fillable = [
        'vendor_id',
        'order_id',
        'order_number',
        'entry_date',
        'notes',
        'entered_by_user_id',
        'entered_by_name',
    ];

    protected $casts = [
        'id' => 'string',
        'vendor_id' => 'string',
        'order_id' => 'string',
        'entered_by_user_id' => 'string',
        'entry_date' => 'date',
    ];
}
