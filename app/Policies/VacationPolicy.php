<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vacation;
use App\Policies\Concerns\ChecksOwnership;
use App\Policies\Concerns\HasAdminBypass;

class VacationPolicy
{
    use ChecksOwnership;
    use HasAdminBypass;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Vacation $vacation): bool
    {
        return $this->owns($user, $vacation);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Vacation $vacation): bool
    {
        // Nur Antragssteller darf ändern, solange noch ausstehend
        return $this->owns($user, $vacation) && $vacation->status === Vacation::STATUS_PENDING;
    }

    public function delete(User $user, Vacation $vacation): bool
    {
        return $this->owns($user, $vacation) && $vacation->status === Vacation::STATUS_PENDING;
    }

    /** Genehmigen / Ablehnen darf nur der Admin (via HasAdminBypass). */
    public function decide(User $user, Vacation $vacation): bool
    {
        return false; // wird durch HasAdminBypass::before() für Admins auf true gesetzt
    }

    /** Stornieren darf der Eigentümer, sofern noch nicht entschieden. */
    public function cancel(User $user, Vacation $vacation): bool
    {
        return $this->owns($user, $vacation)
            && in_array($vacation->status, [Vacation::STATUS_PENDING, Vacation::STATUS_APPROVED], true);
    }
}
