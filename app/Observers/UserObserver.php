<?php

namespace App\Observers;

use App\Models\User;
use App\Support\LookupCache;

class UserObserver
{
    public function saved(User $user): void
    {
        LookupCache::forgetUserDropdown();
    }

    public function deleted(User $user): void
    {
        LookupCache::forgetUserDropdown();
    }
}
