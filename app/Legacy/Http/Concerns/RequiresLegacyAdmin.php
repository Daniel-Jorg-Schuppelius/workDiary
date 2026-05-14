<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Legacy\LegacyUser;
use App\Support\LegacyRoleResolver;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

trait RequiresLegacyAdmin {
    private function ensureAdmin(): void {
        abort_if(! LegacyRoleResolver::isAdmin(Auth::user()), 403);
    }

    /** Liefert alle regulären Legacy-User (id > 3) sortiert nach uname.
     *
     * @return Collection<int, LegacyUser>
     */
    private function legacyUsersForSelect(): Collection {
        return LegacyUser::query()->where('id', '>', 3)->orderBy('uname')->get(['id', 'uname']);
    }
}
