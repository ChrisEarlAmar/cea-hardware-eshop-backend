<?php

namespace App\Mail;

use App\Models\Cart;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AbandonedCartMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly Cart $cart)
    {
        $this->cart->loadMissing('items.variant.product', 'user');
    }

    public function build(): self
    {
        return $this
            ->subject('You left something in your cart - CEA Hardware')
            ->view('emails.abandoned-cart', ['cart' => $this->cart]);
    }
}
