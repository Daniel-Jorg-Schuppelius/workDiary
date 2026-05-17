<?php

/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RequiresLegacyAdmin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Legacy\Http\Concerns;

use App\Legacy\Models\LegacyUser;
use App\Legacy\Support\LegacyRoleResolver;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

trait RequiresLegacyAdmin
{
    private function ensureAdmin(): void
    {
        abort_if(! LegacyRoleResolver::isAdmin(Auth::user()), 403);
    }

    /** Liefert alle regulären Legacy-User (id > 3) sortiert nach uname.
     *
     * @return Collection<int, LegacyUser>
     */
    private function legacyUsersForSelect(): Collection
    {
        return LegacyUser::query()->where('id', '>', 3)->orderBy('uname')->get(['id', 'uname']);
    }
}
