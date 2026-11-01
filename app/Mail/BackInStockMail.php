<?php

namespace App\Mail;

use App\Models\StockNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BackInStockMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly StockNotification $notification)
    {
        $this->notification->loadMissing('variant.product');
    }

    public function build(): self
    {
        $productName = $this->notification->variant->product->name;

        return $this
            ->subject("{$productName} is back in stock - CEA Hardware")
            ->view('emails.back-in-stock', ['notification' => $this->notification]);
    }
}
