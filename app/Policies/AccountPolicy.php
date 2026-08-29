<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\User;

class AccountPolicy
{
    /**
     * Only the account's owner may add/remove members or change roles.
     */
    public function manageMembers(User $user, Account $account): bool
    {
        return $user->account_id === $account->id && $user->role === 'owner';
    }
}
