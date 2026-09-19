<?php

namespace Cultpantry\Market\Policies;

use App\Models\User;
use Cultpantry\Market\Models\Market;

class MarketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Market $market): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Market $market): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Market $market): bool
    {
        return $user->isAdmin();
    }

    /**
     * Class-level ability for the XML import form, which doesn't act on
     * one existing Market instance.
     */
    public function import(User $user): bool
    {
        return $user->isAdmin();
    }
}
