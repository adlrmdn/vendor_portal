<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * PC PLM ID reference, synced read-only from the vsm connection's
 * plm_activity table by PcSyncPlmRefs — additive upsert only. Must be
 * reloadable at least hourly (PC spec 6.2).
 */
class PcPlmRef extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $primaryKey = 'id';

    protected $fillable = [
        'plm_id', 'article_name', 'brand', 'season', 'lot',
        'supplier_name', 'planned_qty', 'synced_at',
    ];

    protected $casts = [
        'planned_qty' => 'decimal:2',
        'synced_at' => 'datetime',
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

    public function scopeSearch($query, string $term)
    {
        $like = '%'.mb_strtolower($term).'%';

        return $query->where(function ($q) use ($like) {
            $q->orWhereRaw('LOWER(plm_id) LIKE ?', [$like])
                ->orWhereRaw('LOWER(article_name) LIKE ?', [$like]);
        });
    }
}
