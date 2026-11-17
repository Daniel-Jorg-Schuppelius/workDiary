<?php
/*
 * Created on   : Fri Nov 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MapsTaxCases.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Targets;

use App\Services\Invoicing\Dto\OutgoingTaxCase;

/**
 * Übergabeziel, das den Steuerfall in die Steuerart des Programms übersetzt
 * (MVP-1712). Die Vorprüfung der Übergabe fragt vor dem Senden, ob das Ziel
 * den Fall abbilden kann; ohne Abbildung geht nichts hinaus.
 */
interface MapsTaxCases {
    /** Grund, warum das Programm den Steuerfall nicht abbilden kann; null = abbildbar. Ohne Anfrage an das Programm. */
    public function taxCaseBlocker(int $organizationId, OutgoingTaxCase $case): ?string;
}
