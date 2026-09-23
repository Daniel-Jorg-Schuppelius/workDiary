<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubEventDetailsPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Club;

use App\Enums\Club\ClubGuardianPermission;
use App\Models\Club\{ClubEventDetails, ClubMember};
use App\Models\Platform\User;
use App\Policies\Concerns\HasAdminBypass;
use Illuminate\Support\Carbon;

/**
 * Vereinstermine (MVP-843): Register-Leser sehen alle, Gruppenleitung nur
 * Termine ihrer Zielgruppen; Teilnahmen pflegen darf die Verwaltung oder die
 * Leitung einer Zielgruppe. Ein Mitglied bzw. seine Vertretung darf sich
 * selbst anmelden (Oberfläche folgt mit MVP-845).
 */
class ClubEventDetailsPolicy {
    use ClubAccess;
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $this->canReadRegister($user) || $this->isGroupLead($user);
    }

    public function view(User $user, ClubEventDetails $details): bool {
        return $this->canReadRegister($user) || $this->leadsTargetGroup($user, $details);
    }

    public function create(User $user): bool {
        return $this->canManage($user);
    }

    public function update(User $user, ClubEventDetails $details): bool {
        unset($details);

        return $this->canManage($user);
    }

    public function delete(User $user, ClubEventDetails $details): bool {
        return $this->update($user, $details);
    }

    /** Anmelden, absagen, einladen, spontan ergänzen. */
    public function manageParticipants(User $user, ClubEventDetails $details): bool {
        return $this->canManage($user) || $this->leadsTargetGroup($user, $details);
    }

    /** Anmeldung für ein bestimmtes Mitglied — auch durch das Mitglied selbst oder seine Vertretung. */
    public function registerMember(User $user, ClubEventDetails $details, ClubMember $member): bool {
        if ($this->manageParticipants($user, $details)) {
            return true;
        }
        if ($member->user_id !== null && $member->user_id === $user->id) {
            return true;
        }

        return $this->isGuardianWith($user, $member, ClubGuardianPermission::Register);
    }

    private function leadsTargetGroup(User $user, ClubEventDetails $details): bool {
        if (! $this->isGroupLead($user)) {
            return false;
        }
        $event = $details->event;

        return $event !== null && $event->clubGroups()->where('leader_user_id', $user->id)->exists();
    }

    private function isGuardianWith(User $user, ClubMember $member, ClubGuardianPermission $permission): bool {
        $today = Carbon::today();

        return $member->guardians()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->where(fn($query) => $query->whereNull('valid_from')->orWhere('valid_from', '<', \App\Support\Query\DateRange::dayAfter($today)))
            ->where(fn($query) => $query->whereNull('valid_to')->orWhere('valid_to', '>=', \App\Support\Query\DateRange::day($today)))
            ->get()
            ->contains(fn($guardian): bool => $guardian->allows($permission));
    }
}
