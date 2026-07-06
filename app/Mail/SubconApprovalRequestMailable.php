<?php

namespace App\Mail;

use App\Models\SubconCuttingReport;
use App\Models\SubconOrder;
use App\Services\SubconProductionService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * Sent to the subcon approver when a vendor submits a stage for approval.
 * Carries signed Approve/Reject links so the approver can act from the email.
 */
class SubconApprovalRequestMailable extends Mailable
{
    use Queueable, SerializesModels;

    public SubconOrder $order;

    /** Gate being approved: 'cutting' or 'gramasi'. */
    public string $gate;

    public string $gateLabel;

    public string $approveUrl;

    public string $declineUrl;

    public string $portalUrl;

    /**
     * Per-size submitted data rows:
     * [['size'=>, 'order_qty'=>?int, 'qty'=>, 'balance'=>?int, 'gramasi'=>], ...].
     * order_qty / balance are null when VSM has no matching production line.
     */
    public array $rows;

    public int $totalQty;

    /** Sum of order quantities across rows that resolved a VSM line. */
    public int $totalOrder;

    /** Total balance (cut − order) across resolved rows; null if none resolved. */
    public ?int $totalBalance;

    public function __construct(SubconOrder $order, string $gate)
    {
        $this->order = $order;
        $this->gate = $gate;
        $this->gateLabel = $gate === 'gramasi' ? 'Gramasi & Blister Capacity' : 'Cutting Report';

        // Relative signature (absolute: false) — the signed:relative middleware
        // validates against path + query only, so it survives the reverse proxy
        // where the internal request host/scheme differs from the public URL.
        $this->approveUrl = url(URL::signedRoute('subcon.approve', ['order' => $order->id, 'gate' => $gate], absolute: false));
        $this->declineUrl = url(URL::signedRoute('subcon.decline', ['order' => $order->id, 'gate' => $gate], absolute: false));
        $this->portalUrl = route('subcon.admin.orders.view', $order->id);

        // Resolve the ordered quantity per production line (by prod_id) from VSM
        // so the approver sees order vs cut vs balance. Best-effort: if VSM is
        // unreachable the map is empty and order_qty/balance fall back to null.
        $orderQtyByProd = [];
        foreach (app(SubconProductionService::class)->forPo($order->order_number) as $g) {
            foreach (($g['lines'] ?? []) as $line) {
                $orderQtyByProd[$line->ProdId] = (int) $line->Qty;
            }
        }

        // Embed the submitted per-size figures so the approver can review in-email.
        $reports = SubconCuttingReport::where('order_id', $order->id)
            ->orderBy('size')
            ->get();

        $totalOrder = 0;
        $anyOrder = false;

        $this->rows = $reports->map(function ($r) use ($orderQtyByProd, &$totalOrder, &$anyOrder) {
            $orderQty = $orderQtyByProd[$r->prod_id] ?? null;
            $cut = (int) $r->cutting_qty;
            if ($orderQty !== null) {
                $totalOrder += $orderQty;
                $anyOrder = true;
            }

            return [
                'size' => $r->size ?: '—',
                'order_qty' => $orderQty,
                'qty' => $cut,
                'balance' => $orderQty !== null ? $cut - $orderQty : null,
                'gramasi' => $r->gramasi !== null ? (float) $r->gramasi : null,
            ];
        })->all();

        $this->totalQty = (int) $reports->sum('cutting_qty');
        $this->totalOrder = $totalOrder;
        $this->totalBalance = $anyOrder ? $this->totalQty - $totalOrder : null;
    }

    public function envelope(): Envelope
    {
        $ref = $this->subjectRef();

        return new Envelope(
            from: new Address('rpa@megaperintis.co.id', 'Mega Perintis RPA'),
            subject: 'Approval needed: '.$this->gateLabel.' — '.$ref,
        );
    }

    /** Style — PO — production group, omitting any blank parts. */
    private function subjectRef(): string
    {
        $parts = array_filter([
            trim((string) $this->order->title),
            trim((string) $this->order->order_number),
            trim((string) $this->order->production_group),
        ], fn ($p) => $p !== '');

        return implode(' — ', $parts);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.subcon-approval-request');
    }
}
