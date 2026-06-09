<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WhistleblowingCasePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Whistleblowing\WhistleblowingCase;

/**
 * Autorisierung fuer Hinweisgeberfaelle (Abschnitt 5 / 25).
 *
 * BEWUSST OHNE {@see \App\Policies\Concerns\HasAdminBypass}: Plattform- und
 * Org-Admins erhalten KEINEN automatischen Zugriff auf Meldeinhalte. Jeder
 * inhaltliche Zugriff verlangt sowohl die feingranulare Permission ALS AUCH
 * eine aktive Fall-Zuweisung (oder eine dokumentierte Notfallfreigabe, Phase 2).
 *
 * Wichtig: Per-Fall-Pruefungen laufen ueber diese Policy-Methoden (Abilities
 * ohne Punkt). Die dotted Permission-Strings duerfen NICHT direkt als
 * Gate-Ability genutzt werden, da der Gate::before-Hook sie kurzschliessen
 * wuerde (Permission-Besitz ohne Zuweisung). Hier werden sie nur intern via
 * hasEffectivePermission() geprueft.
 */
class WhistleblowingCasePolicy {
    public function viewAny(User $user): bool {
        return $user->hasEffectivePermission('whistleblowing.case.viewAny');
    }

    public function view(User $user, WhistleblowingCase $case): bool {
        return $this->authorized($user, $case, 'whistleblowing.case.view');
    }

    public function process(User $user, WhistleblowingCase $case): bool {
        return $this->authorized($user, $case, 'whistleblowing.case.process');
    }

    public function message(User $user, WhistleblowingCase $case): bool {
        return $this->authorized($user, $case, 'whistleblowing.case.message');
    }

    public function note(User $user, WhistleblowingCase $case): bool {
        return $this->authorized($user, $case, 'whistleblowing.case.note');
    }

    public function export(User $user, WhistleblowingCase $case): bool {
        return $this->authorized($user, $case, 'whistleblowing.case.export');
    }

    public function close(User $user, WhistleblowingCase $case): bool {
        return $this->authorized($user, $case, 'whistleblowing.case.close');
    }

    public function retention(User $user, WhistleblowingCase $case): bool {
        return $this->authorized($user, $case, 'whistleblowing.case.retention');
    }

    /**
     * Zuweisen verwaltet die Bearbeiterliste und ist daher NICHT an eine
     * eigene Zuweisung gebunden – aber an Permission und Mandant.
     */
    public function assign(User $user, WhistleblowingCase $case): bool {
        return $this->sameOrganization($user, $case)
            && $user->hasEffectivePermission('whistleblowing.case.assign');
    }

    /** Jede berechtigte Person darf sich SELBST wegen Konflikts sperren. */
    public function declareConflict(User $user, WhistleblowingCase $case): bool {
        return $this->sameOrganization($user, $case)
            && $user->hasEffectivePermission('whistleblowing.case.view');
    }

    /**
     * Notfallfreigabe ERTEILEN: braucht die Emergency-Permission, gleiche Org
     * und darf nicht durch eine selbst konfliktbehaftete Person erfolgen.
     */
    public function grantEmergency(User $user, WhistleblowingCase $case): bool {
        return $this->sameOrganization($user, $case)
            && $user->hasEffectivePermission('whistleblowing.case.emergency')
            && ! $case->isBlockedFor($user);
    }

    /**
     * Inhaltlicher Zugriff: gleiche Organisation UND Permission UND NICHT
     * gesperrt (Interessenkonflikt oder benannter Betroffener) UND (aktive
     * Zuweisung ODER aktive Notfallfreigabe).
     */
    private function authorized(User $user, WhistleblowingCase $case, string $permission): bool {
        return $this->sameOrganization($user, $case)
            && $user->hasEffectivePermission($permission)
            && ! $case->isBlockedFor($user)
            && ($case->isAssigned($user) || $case->hasActiveEmergencyGrantFor($user));
    }

    private function sameOrganization(User $user, WhistleblowingCase $case): bool {
        $caseOrg = $case->getAttribute('organization_id');

        return $caseOrg !== null && (int) $user->organization_id === (int) $caseOrg;
    }
}
