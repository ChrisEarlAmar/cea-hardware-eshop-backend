<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    /**
     * Create a new account and make the current user its owner.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->account_id !== null) {
            throw ValidationException::withMessages([
                'account' => ['You already belong to an account.'],
            ]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $account = Account::create(['name' => $validated['name']]);

        $user->update(['account_id' => $account->id, 'role' => 'owner']);

        return ApiResponse::success($account, status: 201);
    }

    /**
     * Join an existing account as a plain member. There is deliberately no
     * invite/token system here - joining just needs the account's id. That's
     * a real simplification (a production app would gate this behind an
     * invite), acceptable here since there is no way to discover another
     * account's id without already being told it out of band.
     */
    public function join(Request $request, Account $account): JsonResponse
    {
        $user = $request->user();

        if ($user->account_id !== null) {
            throw ValidationException::withMessages([
                'account' => ['You already belong to an account.'],
            ]);
        }

        $user->update(['account_id' => $account->id, 'role' => 'member']);

        return ApiResponse::success($account->fresh());
    }

    /**
     * List the members of the current user's account. Always scoped through
     * the authenticated user's own account_id - never accepts an account id
     * from the client - so there is no way to request another account's
     * member list.
     */
    public function members(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->account_id === null) {
            return ApiResponse::error('no_account', 'You do not belong to an account yet.', 404);
        }

        return ApiResponse::success($user->account->users()->get());
    }

    /**
     * Remove a member from the current user's account. Only the account's
     * owner may do this, and only for members of their own account - a
     * member id from a different account 404s rather than 403s, so a caller
     * can't even tell whether that id belongs to some other account.
     */
    public function removeMember(Request $request, User $targetUser): JsonResponse
    {
        $user = $request->user();

        if ($user->account_id === null || $targetUser->account_id !== $user->account_id) {
            abort(404);
        }

        Gate::authorize('manageMembers', $user->account);

        if ($targetUser->is($user)) {
            throw ValidationException::withMessages([
                'user' => ['You cannot remove yourself from the account.'],
            ]);
        }

        $targetUser->update(['account_id' => null, 'role' => null]);

        return ApiResponse::success(['message' => 'Member removed.']);
    }
}
