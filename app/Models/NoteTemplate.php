<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * The 9 standard numbered contract clauses, versioned by effective date
 * (PC spec 6.1/8). Frozen onto contract_note at approve time — never read
 * for an already-approved contract.
 */
class NoteTemplate extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $primaryKey = 'id';

    protected $fillable = [
        'clause_number', 'body', 'emphasis', 'effective_from', 'effective_to', 'is_active', 'created_by',
    ];

    protected $casts = [
        'emphasis' => 'boolean',
        'is_active' => 'boolean',
        'clause_number' => 'integer',
        'effective_from' => 'date',
        'effective_to' => 'date',
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

    public function scopeEffectiveOn($query, $date)
    {
        return $query->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date);
            });
    }
}
