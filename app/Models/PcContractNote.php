<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Frozen once by PcContractFreezeService at confirm — never updated again. */
class PcContractNote extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $primaryKey = 'id';

    protected $fillable = [
        'contract_id', 'clause_number', 'body', 'emphasis', 'source_note_template_id',
    ];

    protected $casts = [
        'clause_number' => 'integer',
        'emphasis' => 'boolean',
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

    public function sourceTemplate()
    {
        return $this->belongsTo(NoteTemplate::class, 'source_note_template_id');
    }
}
