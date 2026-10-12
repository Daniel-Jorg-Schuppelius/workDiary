<?php
/*
 * Created on   : Mon Oct 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContactNumberIssuer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Contracts;

use App\Enums\Numbering\NumberScope;

/**
 * Programm, das Kunden- bzw. Lieferantennummern selbst vergeben kann
 * (MVP-1130). Nur solche Programme stehen auf der Seite „Nummernkreise“ zur
 * Wahl; übertragen wird über {@see ContactSyncer}/{@see SupplierContactSyncer}.
 */
interface ContactNumberIssuer {
    /** @return list<NumberScope> Kreise, die das Programm vergeben kann (Kunde, Lieferant). */
    public function issuableNumberScopes(): array;
}
