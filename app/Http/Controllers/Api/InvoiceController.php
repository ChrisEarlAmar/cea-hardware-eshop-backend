<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The one endpoint in this codebase that legitimately does NOT go through
 * App\Support\ApiResponse - it returns a binary PDF download rather than a
 * JSON envelope, so there is no "data"/"error" shape to produce here.
 */
class InvoiceController extends Controller
{
    public function show(Request $request, Order $order): Response
    {
        if ($order->user_id !== $request->user()->id) {
            abort(404);
        }

        $order->load(['items', 'shippingAddress', 'billingAddress']);

        $pdf = Pdf::loadView('invoices.order', ['order' => $order]);

        return $pdf->download("invoice-{$order->id}.pdf");
    }
}
