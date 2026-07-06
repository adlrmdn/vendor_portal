<?php

namespace App\Notifications;

use App\Models\ToleranceAmendmentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ToleranceRequestNotification extends Notification
{
    use Queueable;

    protected $request;

    public function __construct(ToleranceAmendmentRequest $request)
    {
        $this->request = $request;
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        $typeLabel = $this->request->type === 'partial_shipment' ? 'Partial Shipment' : 'Tolerance Amendment';
        $poNumber = $this->request->poItem->purchaseOrder->po_number;

        return [
            'title' => 'New '.$typeLabel.' Request',
            'message' => 'Item '.$this->request->poItem->item_number.' (PO: '.$poNumber.') requires approval.',
            'url' => route('admin.purchase-order.view', $this->request->poItem->purchaseOrder->id),
            'icon' => 'fas fa-file-invoice-dollar',
            'type' => 'request',
        ];
    }
}
