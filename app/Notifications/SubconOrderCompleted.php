<?php

namespace App\Notifications;

use App\Models\SubconOrder;
use Illuminate\Notifications\Notification;

/**
 * In-app notification to subcon admins when a vendor completes a work order.
 */
class SubconOrderCompleted extends Notification
{
    public function __construct(public SubconOrder $order) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Work order completed',
            'message' => $this->order->order_number.' was marked completed by '
                .($this->order->vendor?->name ?? 'the vendor').'.',
            'url' => route('subcon.admin.orders.view', $this->order->id),
            'icon' => 'fas fa-flag-checkered',
        ];
    }
}
