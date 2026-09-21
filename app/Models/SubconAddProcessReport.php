<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SubconAddProcessReport extends Model
{
    use HasFactory;

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
        'production_group',
        'prod_id',
        'operation',
        'size',
        'qty',
        'reject_qty',
    ];

    protected $casts = [
        'id' => 'string',
        'qty' => 'integer',
        'reject_qty' => 'integer',
    ];
}
