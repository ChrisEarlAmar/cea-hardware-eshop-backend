<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>You left something in your cart</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; background:#f6f6fc; padding:24px; color:#151875;">
    <div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:8px;overflow:hidden;">
        <div style="background:#2f1ac4;padding:20px 24px;">
            <h1 style="color:#ffffff;margin:0;font-size:20px;">CEA Hardware</h1>
        </div>
        <div style="padding:24px;">
            <h2 style="margin-top:0;">Still thinking it over, {{ $cart->user->name }}?</h2>
            <p>You left these items in your cart. They're still there, whenever you're ready.</p>

            <table style="width:100%;border-collapse:collapse;margin-top:16px;">
                <thead>
                    <tr style="text-align:left;font-size:12px;text-transform:uppercase;color:#8a8fb9;border-bottom:1px solid #eeeffb;">
                        <th style="padding:8px 0;">Product</th>
                        <th style="padding:8px 0;">SKU</th>
                        <th style="padding:8px 0;">Qty</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($cart->items as $item)
                        <tr style="border-bottom:1px solid #f6f6fc;">
                            <td style="padding:8px 0;">{{ $item->variant->product->name }}</td>
                            <td style="padding:8px 0;font-family:monospace;">{{ $item->variant->sku }}</td>
                            <td style="padding:8px 0;">{{ $item->quantity }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <p style="text-align:center;margin-top:24px;">
                <a href="{{ rtrim(config('app.frontend_url'), '/') }}/cart"
                   style="display:inline-block;background:#fb2e86;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:24px;font-weight:bold;">
                    Return to your cart
                </a>
            </p>

            <p style="font-size:13px;color:#8a8fb9;margin-top:32px;">
                This is a personal learning project. If you'd rather not get these reminders, no action is
                needed - this is a one-time email per cart, not a recurring subscription.
            </p>
        </div>
    </div>
</body>
</html>
