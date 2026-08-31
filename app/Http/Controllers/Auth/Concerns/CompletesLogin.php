<?php
/*
 * Created on   : Sun Aug 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CompletesLogin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Auth\Concerns;

use App\Legacy\LegacyBridge;
use App\Models\{SsoConnection, User};
use Illuminate\Support\Facades\DB;

/**
 * Nacharbeiten, die zu einem **abgeschlossenen** Login gehören.
 *
 * Sie liefen bisher direkt nach `Auth::attempt()` — also auch dann, wenn danach
 * noch eine Zwei-Faktor-Abfrage kam und der Login gar nicht zustande kam
 * (Sicherheitsscan 2026-08-23, S-51). Wer nur das Passwort kannte, löste damit
 * Nachbereitung für einen Login aus, den er nie abgeschlossen hat.
 *
 * Jetzt rufen beide Wege dieselben Schritte am selben Punkt auf: der
 * Login-Controller für Konten ohne zweiten Faktor, der Challenge-Controller
 * nach bestandener Abfrage.
 */
trait CompletesLogin {
    /**
     * Break-Glass-Anmeldung protokollieren: ein Konto mit `sso_exempt` hat sich
     * an einer erzwungenen SSO-Verbindung vorbei angemeldet. Das ist erlaubt,
     * aber nichts, was unbemerkt bleiben darf.
     */
    protected function auditBreakGlassIfApplicable(User $user): void {
        if (! $user->sso_exempt) {
            return;
        }

        $connection = SsoConnection::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $user->organization_id)
            ->where('active', true)
            ->where('enforced', true)
            ->first();

        $connection?->audit('sso.break_glass_used', ['user_id' => $user->id]);
    }

    /** Verknüpfung mit dem Legacy-Konto nachziehen (Best-Effort). */
    protected function syncLegacyUserIdIfMissing(User $user, string $submittedUsername): void {
        if ((int) ($user->legacy_user_id ?? 0) > 0 || $submittedUsername === '') {
            return;
        }

        if (! filled(config('database.connections.legacy.database'))) {
            return;
        }

        try {
            // attempt(): kein Connect-Versuch bei als down markierter legacy-DB; Mapping ist Best-Effort.
            $legacy = LegacyBridge::attempt(function () use ($submittedUsername): ?object {
                // Nur der eingegebene Anmeldename — seine Kenntnis setzt das
                // Passwort voraus. Der frühere Rückfall auf $user->name hing
                // die Verknüpfung an ein frei wählbares Profilfeld: wer sich
                // wie ein Legacy-Admin nannte, erbte dessen ID (S-01).
                return DB::connection('legacy')
                    ->table('user')
                    ->select(['id', 'uname'])
                    ->where('uname', $submittedUsername)
                    ->first();
            }, null);

            if ($legacy && (int) $legacy->id > 0) {
                $user->legacy_user_id = (int) $legacy->id;
                $user->save();
            }
        } catch (\Throwable) {
            // Legacy-Mapping ist ein Best-Effort und darf den Login nicht blockieren.
        }
    }
}
