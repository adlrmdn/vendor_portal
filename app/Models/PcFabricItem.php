<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * PC fabric item master, synced read-only from D365 ReleasedProductsV2 +
 * ProductTranslations by PcSyncFabricItems — additive upsert only.
 */
class PcFabricItem extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $primaryKey = 'id';

    protected $fillable = [
        'item_number', 'item_name', 'composition', 'construction', 'weave',
        'cut_width', 'full_width', 'weight_gsm', 'process', 'finish',
        'uom_code', 'is_active', 'synced_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'weight_gsm' => 'decimal:2',
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

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Fast multi-field search over the item catalog (PC spec section 7) —
     * matches on name, spec fields, and item number together. Uses
     * LOWER()/LIKE rather than Postgres-only ILIKE so it also works against
     * the sqlite connection the test suite runs on.
     */
    public function scopeSearch($query, string $term)
    {
        $words = array_filter(preg_split('/\s+/', trim($term)));
        $fields = ['item_name', 'item_number', 'composition', 'construction', 'weave', 'process', 'finish'];

        foreach ($words as $word) {
            $like = '%'.mb_strtolower($word).'%';
            $query->where(function ($q) use ($fields, $like) {
                foreach ($fields as $field) {
                    $q->orWhereRaw('LOWER('.$field.') LIKE ?', [$like]);
                }
            });
        }

        return $query;
    }
}
