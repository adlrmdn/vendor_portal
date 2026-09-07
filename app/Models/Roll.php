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

    /**
     * The content encoded into this roll's QR code. Rendered on demand
     * (see AdminController/VendorController::rollQrCode) rather than
     * generated to a stored file, so it's always derived from live data —
     * no stale file when roll_number/weight/unit change on re-sequencing.
     */
    public function qrPayload(): string
    {
        return json_encode([
            'roll_number' => $this->roll_number,
            'po_number' => $this->item->purchaseOrder->po_number,
            'item_number' => $this->item->item_number,
            'quantity' => $this->weight,
            'unit' => $this->unit,
        ]);
    }

    /**
     * Formatted "quantity unit" caption using this roll's primary metric
     * column for its stored unit (see CLAUDE.md unit-to-column mapping).
     */
    public function qtyCaption(): string
    {
        $qty = match ($this->unit) {
            'YD' => $this->length_yd,
            'M' => $this->length_m,
            default => $this->weight,
        };

        return number_format((float) $qty, 2).' '.$this->unit;
    }

    /**
     * Canonical roll number: {sanitized PO number}-{item number}-{sequence:03d}.
     * The PO number is passed through qrSafeName() so the stored roll_number
     * itself is always filename/display-safe — never a fresh transform layered
     * on top of it later.
     */
    public static function buildRollNumber(string $poNumber, string $itemNumber, int $sequence): string
    {
        return sprintf('%s-%s-%03d', self::qrSafeName($poNumber), $itemNumber, $sequence);
    }

    /**
     * Starting sequence number for (re)numbering a PoItem's rolls. A PO can carry
     * more than one po_items row for the same item_number — a D365 line split, or
     * the vendor-portal's own partial-shipment "-P2" shadow item — and each row's
     * rolls are otherwise sequenced from 1 independently. Since buildRollNumber()
     * keys roll_number on item_number (not the po_item id), that collides two
     * different rolls onto the same printed/scanned roll_number. Starting above
     * the highest sequence already used by a sibling row keeps roll_number unique
     * within the PO for as long as sibling rows aren't resequenced concurrently.
     */
    public static function siblingSequenceOffset(\App\Models\PoItem $item): int
    {
        $siblingItemIds = \App\Models\PoItem::where('po_id', $item->po_id)
            ->where('item_number', $item->item_number)
            ->where('id', '!=', $item->id)
            ->pluck('id');

        if ($siblingItemIds->isEmpty()) {
            return 0;
        }

        return (int) self::whereIn('item_id', $siblingItemIds)->max('sequence');
    }

    /**
     * Filesystem-safe form of a roll/PO number. Raw PO numbers embed a
     * company-code segment (e.g. "MPG/PO/2607/00038") — it's dropped and any
     * remaining "/" is flattened to "-" so the value never implies
     * subdirectories that don't exist. Idempotent: safe to call on an already
     * -sanitized value. E.g. "MPG/PO/2607/00038" -> "PO-2607-00038".
     */
    public static function qrSafeName(string $rollNumber): string
    {
        $name = preg_replace('#^[A-Za-z0-9]+/#', '', $rollNumber, 1);

        return str_replace('/', '-', $name);
    }

    public function markAsPrinted()
    {
        $this->is_printed = true;
        $this->printed_at = now();
        $this->save();

        return $this;
    }
}
