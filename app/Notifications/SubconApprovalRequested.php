<?php

namespace App\Notifications;

use App\Models\SubconOrder;
use Illuminate\Notifications\Notification;

/**
 * In-app notification to subcon admins when a vendor submits a stage for approval.
 */
class SubconApprovalRequested extends Notification
{
    public function __construct(
        public SubconOrder $order,
        public string $gate, // 'cutting' | 'gramasi'
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $label = $this->gate === 'gramasi' ? 'Gramasi & blister' : 'Cutting report';

        return [
            'title' => 'Approval needed',
            'message' => $label.' submitted for '.$this->order->order_number
                .' by '.($this->order->vendor?->name ?? 'vendor').'.',
            'url' => route('subcon.admin.orders.view', $this->order->id),
            'icon' => 'fas fa-gavel',
        ];
    }
}
