<?php
/*
 * Created on   : Mon Oct 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContactNumberPreview.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Contracts;

use App\Enums\Numbering\NumberScope;

/**
 * {@see ContactNumberIssuer}, das die nächste Nummer vorab nennen kann
 * (MVP-1149, Seite „Nummernkreise“). Nur Anzeige: Die Nummer ist nicht
 * reserviert und gilt erst, wenn das Programm sie beim Übertragen vergibt.
 */
interface ContactNumberPreview {
    /** Nächste Nummer des Kreises; null, wenn unbekannt oder das Programm nicht antwortet. */
    public function nextContactNumber(int $organizationId, NumberScope $scope): ?string;
}
