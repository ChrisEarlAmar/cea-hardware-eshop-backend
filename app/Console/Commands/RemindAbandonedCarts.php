<?php

namespace App\Console\Commands;

use App\Mail\AbandonedCartMail;
use App\Models\Cart;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Only ever reminds a signed-in user - a guest cart has no email address on
 * file until checkout, so there's nobody to send it to. Intended to run
 * hourly (see routes/console.php's schedule); each eligible cart is only
 * ever reminded once per round of inactivity (see
 * Cart::abandoned_reminder_sent_at and CartController::addItem, which
 * clears it on new activity).
 */
class RemindAbandonedCarts extends Command
{
    protected $signature = 'orders:remind-abandoned-carts {--hours=2 : How long a cart must sit untouched before it counts as abandoned}';

    protected $description = 'Email signed-in users whose cart has items but has seen no activity for a while';

    public function handle(): int
    {
        $staleSince = now()->subHours((int) $this->option('hours'));

        $carts = Cart::query()
            ->whereNotNull('user_id')
            ->whereNull('abandoned_reminder_sent_at')
            ->whereHas('items', fn ($query) => $query->where('updated_at', '<=', $staleSince))
            ->with('items')
            ->get();

        $sent = 0;

        foreach ($carts as $cart) {
            if ($cart->items->isEmpty()) {
                continue;
            }

            Mail::to($cart->user)->queue(new AbandonedCartMail($cart));
            $cart->update(['abandoned_reminder_sent_at' => now()]);
            $sent++;
        }

        $this->info("Queued {$sent} abandoned-cart reminder(s).");

        return self::SUCCESS;
    }
}
