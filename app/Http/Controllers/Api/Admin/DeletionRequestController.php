<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Staff-only view of every pending self-service deletion request, across
 * every account - see App\Http\Controllers\Api\Admin\OrderController for
 * the same cross-account visibility pattern.
 *
 * Deliberately read-only: actually deleting the account/data is NOT built
 * here. That is a real simplification worth calling out - in production
 * this would be a manual, audited admin action (or an async job behind a
 * grace period), never a same-request hard delete triggered from a list
 * endpoint like this one.
 */
class DeletionRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = User::query()
            ->whereNotNull('deletion_requested_at')
            ->orderBy('deletion_requested_at')
            ->paginate($validated['per_page'] ?? 25);

        return ApiResponse::success(
            $paginator->getCollection()->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'deletion_requested_at' => $user->deletion_requested_at->toIso8601String(),
            ]),
            meta: [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }
}
