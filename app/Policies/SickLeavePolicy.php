<?php

/*
 * Created on   : Mon May 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SickLeavePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Models\Attachment;
use App\Models\SickLeave;
use App\Models\User;
use App\Policies\Concerns\ChecksOwnership;
use App\Policies\Concerns\HasAdminBypass;

class SickLeavePolicy
{
    use ChecksOwnership;
    use HasAdminBypass;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SickLeave $sickLeave): bool
    {
        return $this->owns($user, $sickLeave);
    }

    public function create(User $user): bool
    {
        return true;
    }

    /** Eigene, noch nicht stornierte Krankmeldungen darf der Mitarbeiter korrigieren. */
    public function update(User $user, SickLeave $sickLeave): bool
    {
        return $this->owns($user, $sickLeave) && ! $sickLeave->isCancelled();
    }

    public function delete(User $user, SickLeave $sickLeave): bool
    {
        // Echtes Löschen nur für Admin (über HasAdminBypass::before()).
        return false;
    }

    public function cancel(User $user, SickLeave $sickLeave): bool
    {
        return $this->owns($user, $sickLeave) && ! $sickLeave->isCancelled();
    }

    /** Download der AU-Bescheinigung: Eigentümer der Krankmeldung oder Admin. */
    public function downloadAttachment(User $user, SickLeave $sickLeave, Attachment $attachment): bool
    {
        if (! $this->owns($user, $sickLeave)) {
            return false;
        }

        return $attachment->attachable_type === SickLeave::class
            && (int) $attachment->attachable_id === (int) $sickLeave->id;
    }
}
