<?php

/*
 * Created on   : Tue May 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DutyPlanPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Models\DutyPlan;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

class DutyPlanPolicy
{
    use HasAdminBypass;

    /**
     * Admins dürfen alles außer `delete` – ein veröffentlichter Plan
     * muss zuerst zurückgezogen werden.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($ability === 'delete') {
            return null; // immer die spezifische delete-Logik anwenden
        }

        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, DutyPlan $dutyPlan): bool
    {
        return $user->organization_id === $dutyPlan->organization_id;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, DutyPlan $dutyPlan): bool
    {
        return $user->isAdmin() && $user->organization_id === $dutyPlan->organization_id;
    }

    public function delete(User $user, DutyPlan $dutyPlan): bool
    {
        return $user->isAdmin()
            && $user->organization_id === $dutyPlan->organization_id
            && $dutyPlan->isDraft();
    }
}
