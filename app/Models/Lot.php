<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Lot extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $primaryKey = 'id';

    protected $fillable = [
        'code', 'label', 'is_active', 'source', 'created_by',
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

    public static function addedByUser(array $attributes, User $actor): self
    {
        $model = static::create($attributes + [
            'source' => 'user',
            'created_by' => $actor->id,
        ]);

        PcAuditLog::record([
            'actor' => $actor->email,
            'actor_role' => $actor->role,
            'event_type' => 'master_data.lot.add',
            'entity_type' => 'lot',
            'entity_id' => $model->id,
            'entity_label' => $model->code,
        ]);

        return $model;
    }
}
