<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class HsCode extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $primaryKey = 'id';

    protected $fillable = [
        'code', 'description', 'is_active', 'source', 'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
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
     * Standardizes HS code string to dot notation: e.g. 54076990 -> 5407.69.90
     */
    public static function formatCode(?string $raw): string
    {
        if (! $raw) {
            return '';
        }

        $clean = preg_replace('/[^0-9]/', '', (string) $raw);
        if (strlen($clean) === 8) {
            return substr($clean, 0, 4) . '.' . substr($clean, 4, 2) . '.' . substr($clean, 6, 2);
        }
        if (strlen($clean) === 10) {
            // If 10 digits with trailing zeros (like 5407699000), standard 8-digit is 5407.69.90
            return substr($clean, 0, 4) . '.' . substr($clean, 4, 2) . '.' . substr($clean, 6, 2);
        }

        return (string) $raw;
    }

    public function getFormattedCodeAttribute(): string
    {
        return static::formatCode($this->code);
    }

    /**
     * Preparer/admin self-service add, no approval step (PC spec 6.5).
     */
    public static function addedByUser(array $attributes, User $actor): self
    {
        $model = static::create($attributes + [
            'source' => 'user',
            'created_by' => $actor->id,
        ]);

        PcAuditLog::record([
            'actor' => $actor->email,
            'actor_role' => $actor->role,
            'event_type' => 'master_data.hs_code.add',
            'entity_type' => 'hs_code',
            'entity_id' => $model->id,
            'entity_label' => $model->code,
        ]);

        return $model;
    }
}
