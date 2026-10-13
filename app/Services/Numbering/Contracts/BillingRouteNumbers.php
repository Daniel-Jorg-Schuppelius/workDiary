<?php
/*
 * Created on   : Mon Oct 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillingRouteNumbers.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Numbering\Contracts;

use App\Models\Platform\Organization;

/**
 * Wer Rechnungs-, Gutschrift- und Stornonummern vergibt, entscheidet der
 * Fakturierungsweg (Feature 045, Modul „billing“). Die Seite „Nummernkreise“
 * fragt ihn hierüber ab (MVP-1149).
 */
interface BillingRouteNumbers {
    /** Programm des externen Fakturierungswegs der Organisation; null, wenn workDiary abrechnet. */
    public function externalProgram(Organization $organization): ?string;

    /**
     * Kunden mit einem anderen Fakturierungsweg als die Organisation.
     *
     * @return list<array{program: string, count: int, local: bool}> `local`: Der Weg ist workDiary.
     */
    public function deviations(Organization $organization): array;
}
