<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Models\ToleranceAmendmentRequest;

class RequestActionedNotification extends Notification
{
    use Queueable;

    protected $request;

    public function __construct(ToleranceAmendmentRequest $request)
    {
        $this->request = $request;
    }

    public function via($notifiable): array
    {
        return ["database"];
    }

    public function toArray($notifiable): array
    {
        $typeLabel = $this->request->type === "partial_shipment" ? "Partial Shipment" : "Tolerance Amendment";
        $status = ucfirst($this->request->status);
        $poNumber = $this->request->poItem->purchaseOrder->po_number;
        
        $icon = $this->request->status === "approved" ? "fas fa-check-circle" : "fas fa-times-circle";
        
        return [
            "title" => $typeLabel . " " . $status,
            "message" => "Your request for Item " . $this->request->poItem->item_number . " (PO: " . $poNumber . ") has been " . $this->request->status . ".",
            "url" => route("vendor.item.process", $this->request->poItem->id),
            "icon" => $icon,
            "type" => "status_update"
        ];
    }
}
