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
class SubconDailyProductionStyle extends Model
{
    use HasFactory;

    protected $connection = 'vsm';

    protected $table = 'subcon_daily_production_styles';

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

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'order_id',
        'order_number',
        'vendor_name',
        'status',
        'closed_at',
        'closed_by_name',
        'notes',
    ];

    protected $casts = [
        'id' => 'string',
        'order_id' => 'string',
        'closed_at' => 'datetime',
    ];

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }
}
