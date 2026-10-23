<?php
/*
 * Created on   : Fri Jun 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DayClosurePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies\Diary;

use App\Enums\TimeApproval\DayClosureStatus;
use App\Enums\User\Permission as P;
use App\Models\Diary\DayClosure;
use App\Models\Platform\User;
use App\Policies\Concerns\ChecksOwnership;
use App\Services\Approval\SelfApproval;
use App\Services\Users\TeamScope;

/**
 * Berechtigungen für Tagesabschlüsse (MVP-015, ../WorkDiary-Architecture/konzepte/tagesabschluss.md §7).
 *
 * Fachliche Vorbedingungen (Zukunftstag, Monats-Sperre, ⛔-Warnungen)
 * erzwingt zusätzlich der {@see \App\Services\TimeApproval\DayCloseService};
 * die Policy deckt Eigentum, Organisation, Permission und Status ab.
 */
class DayClosurePolicy {
    use ChecksOwnership;

    /** Admin-Durchgriff — außer bei der Korrekturfreigabe, dort gilt die Selbstfreigabe-Regel auch für Admins. */
    public function before(User $user, string $ability): ?bool {
        return $ability !== 'approveCorrection' && $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool {
        return $user->can(P::DayCloseViewOwn->value)
            || $user->can(P::DayCloseViewTeam->value)
            || $user->can(P::DayCloseViewOrganization->value);
    }

    public function view(User $user, DayClosure $closure): bool {
        if (! $this->sharesOrganization($user, $closure)) {
            return false;
        }
        if ($this->owns($user, $closure)) {
            return $user->can(P::DayCloseViewOwn->value);
        }

        return ($user->can(P::DayCloseViewOrganization->value) || $user->can(P::DayCloseViewTeam->value))
            && $this->inScope($user, $closure);
    }

    /** day.save — Audit-Speichern des eigenen, offenen Tages. */
    public function save(User $user, DayClosure $closure): bool {
        return $this->sharesOrganization($user, $closure)
            && $this->owns($user, $closure)
            && $user->can(P::DayCloseViewOwn->value);
    }

    public function close(User $user, DayClosure $closure): bool {
        return $this->sharesOrganization($user, $closure)
            && $this->owns($user, $closure)
            && $user->can(P::DayCloseCloseOwn->value)
            && $closure->status === DayClosureStatus::Open;
    }

    public function requestCorrection(User $user, DayClosure $closure): bool {
        return $this->sharesOrganization($user, $closure)
            && $this->owns($user, $closure)
            && $user->can(P::DayCloseRequestCorrectionOwn->value)
            && $closure->status === DayClosureStatus::Closed;
    }

    /**
     * Korrektur-Entscheidung (§5): Admin/Teamleitung, nie über den eigenen Tag
     * (S-35) — außer niemand sonst darf entscheiden; Team-Recht nur fürs Team.
     */
    public function approveCorrection(User $user, DayClosure $closure): bool {
        if ($closure->status !== DayClosureStatus::Correction) {
            return false;
        }
        if ($this->owns($user, $closure) && ! app(SelfApproval::class)->soleDecider($user, P::DayCloseApproveCorrection)) {
            return false;
        }
        if ($user->isAdmin()) {
            return true;
        }

        return $this->sharesOrganization($user, $closure)
            && $user->can(P::DayCloseApproveCorrection->value)
            && $this->inScope($user, $closure);
    }

    /** Admin-Reopen ohne Antrag (§2.6, Pflicht-Begründung im Service). */
    public function reopen(User $user, DayClosure $closure): bool {
        return $this->sharesOrganization($user, $closure)
            && $user->can(P::DayCloseReopen->value)
            && $this->inScope($user, $closure)
            && $closure->status === DayClosureStatus::Closed;
    }

    private function inScope(User $user, DayClosure $closure): bool {
        return app(TeamScope::class)->covers($user, (int) $closure->user_id, P::DayCloseViewTeam, P::DayCloseViewOrganization);
    }
}
