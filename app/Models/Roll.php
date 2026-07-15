<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Roll extends Model
{
    use HasFactory;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $primaryKey = 'id';

    protected $fillable = [
        'item_id',
        'roll_number',
        'internal_id',
        'vendor_roll_no',
        'bale_no',
        'color',
        'sequence',
        'length',
        'length_yd',
        'length_m',
        'weight',
        'unit',
        'grade',
        'defects',
        'qr_code_path',
        'is_printed',
        'printed_at',
        'notes',
    ];

    protected $casts = [
        'id' => 'string',
        'item_id' => 'string',
        'defects' => 'array',
        'weight' => 'decimal:2',
        'length_yd' => 'decimal:2',
        'length_m' => 'decimal:2',
        'is_printed' => 'boolean',
        'printed_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($roll) {
            // pgsql has a DB-level uuid default; sqlite (dev/tests) does not.
            $roll->id = $roll->id ?: (string) \Illuminate\Support\Str::uuid();
        });
    }

    public function item()
    {
        return $this->belongsTo(PoItem::class, 'item_id');
    }

    public function generateQrCode()
    {
        // Ensure directory exists
        $directory = storage_path('app/public/qrcodes');
        if (! file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        $qrContent = json_encode([
            'roll_number' => $this->roll_number,
            'po_number' => $this->item->purchaseOrder->po_number,
            'item_number' => $this->item->item_number,
            'quantity' => $this->weight,
            'unit' => $this->unit,
        ]);

        $filename = 'qrcodes/'.$this->roll_number.'.png';
        $filepath = storage_path('app/public/'.$filename);

        \SimpleSoftwareIO\QrCode\Facades\QrCode::size(300)->generate($qrContent, $filepath);

        $this->qr_code_path = $filename;
        $this->save();

        return $this;
    }

    public function markAsPrinted()
    {
        $this->is_printed = true;
        $this->printed_at = now();
        $this->save();

        return $this;
    }
}
