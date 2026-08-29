<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Order Confirmation</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; background:#f6f6fc; padding:24px; color:#151875;">
    <div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:8px;overflow:hidden;">
        <div style="background:#2f1ac4;padding:20px 24px;">
            <h1 style="color:#ffffff;margin:0;font-size:20px;">CEA Hardware</h1>
        </div>
        <div style="padding:24px;">
            <h2 style="margin-top:0;">Thanks for your order, {{ $order->user->name }}!</h2>
            <p>Order <strong>#{{ $order->id }}</strong> has been confirmed and is now being prepared.</p>

            <table style="width:100%;border-collapse:collapse;margin-top:16px;">
                <thead>
                    <tr style="text-align:left;font-size:12px;text-transform:uppercase;color:#8a8fb9;border-bottom:1px solid #eeeffb;">
                        <th style="padding:8px 0;">SKU</th>
                        <th style="padding:8px 0;">Qty</th>
                        <th style="padding:8px 0;">Unit price</th>
                        <th style="padding:8px 0;">Line total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($order->items as $item)
                        <tr style="border-bottom:1px solid #f6f6fc;">
                            <td style="padding:8px 0;font-family:monospace;">{{ $item->sku }}</td>
                            <td style="padding:8px 0;">{{ $item->quantity }}</td>
                            <td style="padding:8px 0;">{{ $item->unit_price }}</td>
                            <td style="padding:8px 0;">{{ $item->line_total }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <p style="text-align:right;font-size:16px;font-weight:bold;margin-top:16px;">
                Total: {{ $order->total }}
            </p>

            <p style="font-size:13px;color:#8a8fb9;margin-top:32px;">
                This is a personal learning project running Stripe in test mode - no real payment was processed for this order.
            </p>
        </div>
    </div>
</body>
</html>
