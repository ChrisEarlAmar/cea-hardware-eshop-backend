<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice #{{ $order->id }}</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #151875; font-size: 12px; }
        h1 { color: #2f1ac4; margin: 0 0 4px 0; font-size: 22px; }
        .muted { color: #8a8fb9; }
        .header { overflow: hidden; margin-bottom: 24px; }
        .header .company { float: left; }
        .header .meta { float: right; text-align: right; }
        .addresses { overflow: hidden; margin-bottom: 24px; }
        .addresses .block { float: left; width: 48%; }
        .addresses .block.billing { float: right; text-align: right; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.items th {
            text-align: left; text-transform: uppercase; font-size: 10px; color: #8a8fb9;
            border-bottom: 1px solid #eeeffb; padding: 6px 4px;
        }
        table.items td { padding: 6px 4px; border-bottom: 1px solid #f6f6fc; }
        table.items td.num, table.items th.num { text-align: right; }
        table.totals { width: 260px; margin-left: auto; margin-top: 12px; }
        table.totals td { padding: 4px 0; }
        table.totals td.label { color: #8a8fb9; }
        table.totals td.value { text-align: right; }
        table.totals tr.grand td { font-weight: bold; font-size: 14px; border-top: 1px solid #eeeffb; padding-top: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="company">
            <h1>CEA Hardware</h1>
            <div class="muted">Invoice</div>
        </div>
        <div class="meta">
            <div><strong>Invoice #{{ $order->id }}</strong></div>
            <div class="muted">Order date: {{ $order->created_at->format('M j, Y') }}</div>
        </div>
    </div>

    @if ($order->shippingAddress || $order->billingAddress)
        <div class="addresses">
            @if ($order->shippingAddress)
                <div class="block shipping">
                    <div class="muted">Ship to</div>
                    <div>{{ $order->shippingAddress->recipient_name }}</div>
                    <div>{{ $order->shippingAddress->line1 }}</div>
                    @if ($order->shippingAddress->line2)
                        <div>{{ $order->shippingAddress->line2 }}</div>
                    @endif
                    <div>
                        {{ $order->shippingAddress->city }}, {{ $order->shippingAddress->state }}
                        {{ $order->shippingAddress->postal_code }}
                    </div>
                    <div>{{ $order->shippingAddress->country }}</div>
                </div>
            @endif
            @if ($order->billingAddress)
                <div class="block billing">
                    <div class="muted">Bill to</div>
                    <div>{{ $order->billingAddress->recipient_name }}</div>
                    <div>{{ $order->billingAddress->line1 }}</div>
                    @if ($order->billingAddress->line2)
                        <div>{{ $order->billingAddress->line2 }}</div>
                    @endif
                    <div>
                        {{ $order->billingAddress->city }}, {{ $order->billingAddress->state }}
                        {{ $order->billingAddress->postal_code }}
                    </div>
                    <div>{{ $order->billingAddress->country }}</div>
                </div>
            @endif
        </div>
    @endif

    <table class="items">
        <thead>
            <tr>
                <th>SKU</th>
                <th class="num">Qty</th>
                <th class="num">Unit price</th>
                <th class="num">Line total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr>
                    <td>{{ $item->sku }}</td>
                    <td class="num">{{ $item->quantity }}</td>
                    <td class="num">${{ number_format((float) $item->unit_price, 2) }}</td>
                    <td class="num">${{ number_format((float) $item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        @if ((float) $order->discount_total > 0)
            <tr>
                <td class="label">Discount</td>
                <td class="value">-${{ number_format((float) $order->discount_total, 2) }}</td>
            </tr>
        @endif
        <tr class="grand">
            <td class="label">Total</td>
            <td class="value">${{ number_format((float) $order->total, 2) }}</td>
        </tr>
    </table>

    <p class="muted" style="margin-top:32px;">
        This is a personal learning project running Stripe in test mode - no real payment was processed for this order.
    </p>
</body>
</html>
