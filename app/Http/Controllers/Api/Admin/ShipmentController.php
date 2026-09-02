<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Staff-only: sets tracking info on any order. Deliberately not restricted
 * to a particular order status - a shipment can reasonably be corrected or
 * set early, and that's a staff-trust call rather than a state machine.
 */
class ShipmentController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function update(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'tracking_carrier' => ['required', 'string', 'max:100'],
            'tracking_number' => ['required', 'string', 'max:100'],
        ]);

        $order->update([
            'tracking_carrier' => $validated['tracking_carrier'],
            'tracking_number' => $validated['tracking_number'],
        ]);

        $this->auditLogger->log($request->user(), 'order.tracking_updated', $order, [
            'tracking_carrier' => $validated['tracking_carrier'],
            'tracking_number' => $validated['tracking_number'],
        ]);

        return ApiResponse::success([
            'id' => $order->id,
            'tracking_carrier' => $order->tracking_carrier,
            'tracking_number' => $order->tracking_number,
        ]);
    }
}
