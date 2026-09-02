<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Read-only viewer for the trail every other admin controller writes to via
 * App\Services\AuditLogger - see that class for where rows come from.
 * Staff-only, reachable only via the `staff` middleware (see routes/api.php).
 */
class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'actor_id' => ['sometimes', 'integer'],
            'action' => ['sometimes', 'string'],
            // Date-only ("2026-08-29") or full datetime strings both work -
            // treated as calendar-day bounds rather than exact instants, so
            // a plain date filter behaves the way a human expects.
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = AdminAuditLog::query()
            ->with('actor:id,name,email')
            ->when($validated['actor_id'] ?? null, fn ($q, $actorId) => $q->where('actor_id', $actorId))
            ->when($validated['action'] ?? null, fn ($q, $action) => $q->where('action', 'like', '%'.$action.'%'))
            ->when($validated['from'] ?? null, fn ($q, $from) => $q->where('created_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($validated['to'] ?? null, fn ($q, $to) => $q->where('created_at', '<=', Carbon::parse($to)->endOfDay()))
            ->latest('created_at')
            ->paginate($validated['per_page'] ?? 25);

        return ApiResponse::success(
            $paginator->getCollection()->map(fn (AdminAuditLog $log) => $this->logPayload($log)),
            meta: [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    private function logPayload(AdminAuditLog $log): array
    {
        return [
            'id' => $log->id,
            'actor' => $log->relationLoaded('actor') && $log->actor ? [
                'id' => $log->actor->id,
                'name' => $log->actor->name,
                'email' => $log->actor->email,
            ] : null,
            'action' => $log->action,
            'subject_type' => $log->subject_type,
            'subject_id' => $log->subject_id,
            'metadata' => $log->metadata,
            'created_at' => $log->created_at->toIso8601String(),
        ];
    }
}
