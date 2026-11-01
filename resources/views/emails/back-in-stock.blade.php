<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Back in stock</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; background:#f6f6fc; padding:24px; color:#151875;">
    <div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:8px;overflow:hidden;">
        <div style="background:#2f1ac4;padding:20px 24px;">
            <h1 style="color:#ffffff;margin:0;font-size:20px;">CEA Hardware</h1>
        </div>
        <div style="padding:24px;">
            <h2 style="margin-top:0;">Good news - it's back!</h2>
            <p>
                <strong>{{ $notification->variant->product->name }}</strong>
                (SKU: {{ $notification->variant->sku }}) is back in stock.
            </p>

            <p style="text-align:center;margin-top:24px;">
                <a href="{{ rtrim(config('app.frontend_url'), '/') }}/products/{{ $notification->variant->product->slug }}"
                   style="display:inline-block;background:#fb2e86;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:24px;font-weight:bold;">
                    View product
                </a>
            </p>

            <p style="font-size:13px;color:#8a8fb9;margin-top:32px;">
                You're getting this because you asked to be notified when this item was restocked. This is a
                one-time notification for this signup - if you'd like to hear about the next restock too, you're
                welcome to sign up again.
            </p>
        </div>
    </div>
</body>
</html>
