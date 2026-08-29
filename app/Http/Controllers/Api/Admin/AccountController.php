<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Staff-only, cross-account visibility - see App\Http\Controllers\Api\Admin\OrderController.
 */
class AccountController extends Controller
{
    public function index(): JsonResponse
    {
        $accounts = Account::withCount('users')->orderBy('name')->get();

        return ApiResponse::success($accounts->map(fn (Account $account) => [
            'id' => $account->id,
            'name' => $account->name,
            'member_count' => $account->users_count,
            'created_at' => $account->created_at->toIso8601String(),
        ]));
    }

    public function users(Account $account): JsonResponse
    {
        return ApiResponse::success(
            $account->users()->get()->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ])
        );
    }
}
