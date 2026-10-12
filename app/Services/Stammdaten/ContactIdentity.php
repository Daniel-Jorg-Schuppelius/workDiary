<?php
/*
 * Created on   : Mon Oct 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContactIdentity.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Stammdaten;

use App\Models\Customer\Customer;
use App\Models\Supplier\Supplier;
use App\Services\Integration\Match\{FuzzyField, Normalize};
use App\Services\Integration\Profiles\CustomerMatchProfile;

/**
 * Ist ein Kontakt im Buchhaltungsprogramm derselbe wie der lokale (MVP-1133)?
 * Gefragt, bevor ein Treffer über Nummer, E-Mail oder Name aktualisiert oder
 * für eine Belegübergabe genutzt wird — sonst überschrieb die App fremde
 * Kontakte, die zufällig dieselbe Nummer tragen.
 *
 * Tragen beide eine USt-IdNr., entscheidet sie allein; sonst der Name nach
 * der Ähnlichkeitsregel des Kundenabgleichs.
 */
final class ContactIdentity {
    /** @param array{name?: ?string, company?: ?string, vat_id?: mixed} $remote */
    public function same(Customer|Supplier $party, array $remote): bool {
        $local = [
            'name' => (string) $party->name,
            'company' => (string) ($party->company ?? ''),
            'vat_id' => $party->vat_id,
        ];

        $localVat = Normalize::id($local['vat_id']);
        $remoteVat = Normalize::id($remote['vat_id'] ?? null);
        if ($localVat !== '' && $remoteVat !== '') {
            return $localVat === $remoteVat;
        }

        return (new FuzzyField(['name', 'company'], CustomerMatchProfile::FUZZY_THRESHOLD))->matches($local, $remote);
    }
}
