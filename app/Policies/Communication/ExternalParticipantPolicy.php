<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalParticipantPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Enums\User\Permission;
use App\Models\{ExternalParticipant, User};
use App\Policies\Concerns\HasAdminBypass;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Zugriffsregeln für die interne Verwaltung externer Beteiligter (Feature 033):
 * - admin: alles (before()-Bypass).
 * - Einladen/Widerrufen erfordert die Permission externalParticipant.manage
 *   UND das Bearbeiten-Recht am Subject (Auftrag/Protokoll/Dokument) — wer den
 *   Auftrag nicht bearbeiten darf, lädt auch keine Externen dazu ein.
 *
 * Der öffentliche, login-freie Zugriff der Externen läuft NICHT über diese
 * Policy, sondern token-basiert (Hash + Ablauf + Widerruf) im
 * PublicExternalParticipantController.
 */
class ExternalParticipantPolicy {
    use HasAdminBypass;

    /** Externe an ein Subject einladen. */
    public function manageForSubject(User $user, Model $subject): bool {
        return $user->can(Permission::ExternalParticipantManage->value)
            && Gate::forUser($user)->allows('update', $subject);
    }

    /** Eine bestehende Einladung widerrufen. */
    public function revoke(User $user, ExternalParticipant $participant): bool {
        $subject = $participant->subject()->first();
        if (! $subject instanceof Model) {
            return false;
        }

        return $this->manageForSubject($user, $subject);
    }
}
