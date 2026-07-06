<?php

namespace App\Notifications;

use App\Models\SubconOrder;
use Illuminate\Notifications\Notification;

/**
 * In-app notification to the vendor when an admin approves or declines a stage.
 */
class SubconStageDecision extends Notification
{
    public function __construct(
        public SubconOrder $order,
        public string $gate,    // 'cutting' | 'gramasi'
        public string $outcome, // 'approved' | 'declined'
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $label = $this->gate === 'gramasi' ? 'Gramasi & blister' : 'Cutting report';

        if ($this->outcome === 'approved') {
            $message = $this->gate === 'gramasi'
                ? $label.' approved for '.$this->order->order_number.'. Label printing is now unlocked.'
                : $label.' approved for '.$this->order->order_number.'. You can now enter gramasi & blister capacity.';
            $icon = 'fas fa-check-circle';
        } else {
            $message = $label.' for '.$this->order->order_number.' was returned for changes. Please review and resubmit.';
            $icon = 'fas fa-rotate-left';
        }

        return [
            'title' => $this->outcome === 'approved' ? 'Approved' : 'Returned for changes',
            'message' => $message,
            'url' => route('subcon.vendor.orders.view', $this->order->id),
            'icon' => $icon,
        ];
    }
}
