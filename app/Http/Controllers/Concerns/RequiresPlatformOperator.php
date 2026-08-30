<?php
/*
 * Created on   : Sun Aug 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RequiresPlatformOperator.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Schranke für installationsweite Operationen (Sicherheitsscan 2026-08-23,
 * S-02).
 *
 * **Warum nicht über eine Permission?** Weil das Präfix `platform.` in diesem
 * Code keinen Geltungsbereich beschreibt: `platform.problemReports.manage`
 * gehört zur org-gescopten Fehlermelde-Inbox, `platform.operations.*` zum
 * org-gescopten Aufgabencenter, `platform.support.export` auch zum
 * Supportbericht der **eigenen** Organisation. Ein pauschaler Entzug dieser
 * Rechte hätte org-eigene Funktionen abgeschaltet.
 *
 * Die Mandantengrenze verläuft an der **Aktion**, nicht am Namen des Rechts:
 * Wer die ganze Installation anfasst — System-Einstellungen, System-
 * Wartungsfenster, Scheduler-Overrides ohne Organisation, Supportbericht,
 * Diagnose, Demo-Mandanten, Instanz-Lizenz, Betriebsmetriken,
 * Sicherungsstand — muss Plattform-Betreiber sein. Das gilt sofort und hängt
 * nicht am nächsten Seeder-Lauf.
 */
trait RequiresPlatformOperator {
    /** Plattform-Betreiber? (nicht: Admin der eigenen Organisation) */
    protected function isPlatformOperator(): bool {
        $user = Auth::user();

        return $user instanceof User && $user->isGlobalAdmin();
    }

    /** Bricht mit 403 ab, wenn der Aufrufer kein Plattform-Betreiber ist. */
    protected function assertPlatformOperator(): void {
        abort_unless($this->isPlatformOperator(), 403);
    }
}
