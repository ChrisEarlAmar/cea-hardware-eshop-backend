<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The B2B address book: every address is either "personal" (user_id set,
 * belongs to one signed-in user) or "company" (account_id set, shared with
 * every member of that account) - never both, see Address::$fillable.
 * Ownership for update/destroy is deliberately "owns it personally OR it's
 * their account's shared address" - any member may manage the shared book,
 * not just the account owner, matching this task's read/write B2B pattern
 * (see AccountPolicy for the narrower owner-only actions elsewhere).
 */
class AddressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $personal = Address::where('user_id', $user->id)->get();

        $company = $user->account_id !== null
            ? Address::where('account_id', $user->account_id)->get()
            : collect();

        $addresses = $personal->concat($company)
            ->map(fn (Address $address) => $this->addressPayload($address))
            ->values();

        return ApiResponse::success($addresses);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'state' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'size:2'],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_default' => ['nullable', 'boolean'],
            'shared' => ['nullable', 'boolean'],
        ]);

        $shared = (bool) ($validated['shared'] ?? false);

        if ($shared && $user->account_id === null) {
            throw ValidationException::withMessages([
                'shared' => ['You must belong to an account to create a shared company address.'],
            ]);
        }

        $address = DB::transaction(function () use ($user, $validated, $shared) {
            $address = Address::create([
                'user_id' => $shared ? null : $user->id,
                'account_id' => $shared ? $user->account_id : null,
                'label' => $validated['label'],
                'recipient_name' => $validated['recipient_name'],
                'line1' => $validated['line1'],
                'line2' => $validated['line2'] ?? null,
                'city' => $validated['city'],
                'state' => $validated['state'],
                'postal_code' => $validated['postal_code'],
                'country' => $validated['country'] ?? 'US',
                'phone' => $validated['phone'] ?? null,
                'is_default' => (bool) ($validated['is_default'] ?? false),
            ]);

            if ($address->is_default) {
                $this->unsetOtherDefaults($address);
            }

            return $address;
        });

        return ApiResponse::success($this->addressPayload($address), status: 201);
    }

    public function update(Request $request, Address $address): JsonResponse
    {
        $this->authorizeAccess($request->user(), $address);

        $validated = $request->validate([
            'label' => ['sometimes', 'string', 'max:255'],
            'recipient_name' => ['sometimes', 'string', 'max:255'],
            'line1' => ['sometimes', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'string', 'max:255'],
            'state' => ['sometimes', 'string', 'max:255'],
            'postal_code' => ['sometimes', 'string', 'max:20'],
            'country' => ['sometimes', 'string', 'size:2'],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        DB::transaction(function () use ($address, $validated) {
            $address->update($validated);

            if ($address->is_default) {
                $this->unsetOtherDefaults($address);
            }
        });

        return ApiResponse::success($this->addressPayload($address->fresh()));
    }

    public function destroy(Request $request, Address $address): JsonResponse
    {
        $this->authorizeAccess($request->user(), $address);

        $address->delete();

        return ApiResponse::success(['message' => 'Address deleted.']);
    }

    /**
     * A resource-ownership failure 404s rather than 403s, matching this
     * codebase's convention elsewhere (see CartController/OrderController).
     * "Owns it" means either their own personal address, or a shared
     * address belonging to their own account - never someone else's.
     */
    private function authorizeAccess(User $user, Address $address): void
    {
        $owned = $address->user_id === $user->id
            || ($address->account_id !== null && $address->account_id === $user->account_id);

        if (! $owned) {
            abort(404);
        }
    }

    /**
     * "Default" is scoped separately for personal vs. company addresses -
     * marking a company address as default must not clear a user's personal
     * default, and vice versa. Always called from inside a transaction so
     * the new default and the cleared old ones commit together.
     */
    private function unsetOtherDefaults(Address $address): void
    {
        $query = Address::where('id', '!=', $address->id)->where('is_default', true);

        if ($address->account_id !== null) {
            $query->where('account_id', $address->account_id);
        } else {
            $query->where('user_id', $address->user_id);
        }

        $query->update(['is_default' => false]);
    }

    private function addressPayload(Address $address): array
    {
        return [
            'id' => $address->id,
            'label' => $address->label,
            'recipient_name' => $address->recipient_name,
            'line1' => $address->line1,
            'line2' => $address->line2,
            'city' => $address->city,
            'state' => $address->state,
            'postal_code' => $address->postal_code,
            'country' => $address->country,
            'phone' => $address->phone,
            'is_default' => $address->is_default,
            'scope' => $address->account_id !== null ? 'company' : 'personal',
        ];
    }
}
