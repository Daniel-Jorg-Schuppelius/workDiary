<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CtiDialService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Cti\Dial;

use App\Models\{CtiConnection, Organization};
use CommonToolkit\Helper\Data\PhoneNumberHelper;

/**
 * Click-to-Dial-Einstieg (Feature 056/MVP-118; Audit 2026-08, W4.5):
 * Rufnummer normalisieren, wählfähige Anbindung der Organisation finden,
 * Anruf starten.
 *
 * Bewusst ohne Protokollierung: Der Anruf selbst wird ohnehin über die
 * bestehende Anrufliste/den CTI-Webhook erfasst ({@see \App\Services\Cti\CtiCallService})
 * — ein zweiter Eintrag beim Wählen würde denselben Vorgang doppelt zählen.
 */
class CtiDialService {
    public function __construct(private readonly CtiDialer $dialer) {}

    /**
     * Ist für die Organisation eine wählfähige Anbindung eingerichtet?
     *
     * Bewusst OHNE Memoization: Auf Detailseiten fragen zwar mehrere
     * Rufnummern dieselbe Anbindung ab, aber die Query ist indiziert und
     * billig. Ein Request-Cache (statisch, `once()` oder `scoped`) fror in
     * Tests und unter Octane einen veralteten Stand ein — die eingesparte
     * Query wiegt diese Falle nicht auf.
     */
    public function connectionFor(Organization $organization): ?CtiConnection {
        return CtiConnection::query()
            ->where('organization_id', $organization->id)
            ->where('active', true)
            ->where('dial_enabled', true)
            ->orderBy('id')
            ->first();
    }

    /**
     * Startet den Anruf zur übergebenen Nummer (beliebige Schreibweise).
     *
     * @throws CtiDialException bei fehlender Anbindung, unbrauchbarer Nummer
     *                          oder Ablehnung durch die Anlage
     */
    public function dial(Organization $organization, string $number): void {
        $connection = $this->connectionFor($organization);
        if ($connection === null) {
            throw new CtiDialException((string) __('cti.dial.no_connection'));
        }

        $e164 = trim($number) !== '' ? PhoneNumberHelper::toE164($number, 'DE') : null;
        if ($e164 === null) {
            throw new CtiDialException((string) __('cti.dial.invalid_number'));
        }

        $this->dialer->dial($connection, $e164);
    }
}
