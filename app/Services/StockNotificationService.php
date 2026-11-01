<?php

namespace App\Services;

use App\Mail\BackInStockMail;
use App\Models\ProductVariant;
use App\Models\StockNotification;
use Illuminate\Support\Facades\Mail;

class StockNotificationService
{
    public function subscribe(ProductVariant $variant, string $email, ?int $userId): StockNotification
    {
        // updateOrCreate rather than a plain create so re-signing-up for a
        // variant you already asked about (maybe after an earlier restock
        // that already notified and cleared you) reopens the subscription
        // instead of colliding with the (product_variant_id, email) unique
        // index and 500ing.
        return StockNotification::updateOrCreate(
            ['product_variant_id' => $variant->id, 'email' => $email],
            ['user_id' => $userId, 'notified_at' => null],
        );
    }

    /**
     * Call after any stock increase - the only thing that matters is
     * whether the variant crossed from "nothing to sell" to "something to
     * sell". A restock that goes from 5 to 10 was never out of stock in the
     * first place, so nobody who signed up (there'd be nobody to sign up in
     * that case anyway) gets emailed.
     */
    public function notifyIfBackInStock(ProductVariant $variant, int $previousStock): void
    {
        if ($previousStock > 0 || $variant->stock <= 0) {
            return;
        }

        $pending = $variant->stockNotifications()->whereNull('notified_at')->get();

        foreach ($pending as $notification) {
            Mail::to($notification->email)->queue(new BackInStockMail($notification));
            $notification->update(['notified_at' => now()]);
        }
    }
}
