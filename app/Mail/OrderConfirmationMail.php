<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderConfirmationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly Order $order)
    {
        $this->order->loadMissing('items');
    }

    public function build(): self
    {
        return $this
            ->subject("Order #{$this->order->id} confirmed - CEA Hardware")
            ->view('emails.order-confirmation', ['order' => $this->order]);
    }
}
