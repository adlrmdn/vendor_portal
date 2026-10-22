<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PcContractFile extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $primaryKey = 'id';

    protected $fillable = [
        'contract_id', 'file_type', 'disk', 'path', 'original_filename',
        'mime_type', 'size_bytes', 'revision_no', 'generated_by', 'generated_at',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'revision_no' => 'integer',
        'generated_at' => 'datetime',
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

    public function contract()
    {
        return $this->belongsTo(PcContract::class, 'contract_id');
    }

    public function generatedBy()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /**
     * No public URL — 'local' is a private disk. Callers stream this
     * through an auth-gated controller route, never a direct link.
     */
    public function contents(): string
    {
        return Storage::disk($this->disk)->get($this->path);
    }
}
