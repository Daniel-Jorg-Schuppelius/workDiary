<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PartyDocumentSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing\Contracts;

use App\Models\Customer\Customer;
use App\Models\Supplier\Supplier;
use App\Services\Billing\Dto\PartyDocumentList;
use Carbon\CarbonInterface;

/**
 * Belege eines Kunden oder Lieferanten aus einem Fremdsystem (Buchhaltungs-
 * programm) für die Belegliste der Akte. Plugins tragen sich in
 * {@see \App\Services\Billing\PartyDocumentSources} ein.
 */
interface PartyDocumentSource {
    public function key(): string;

    /** null, solange die Quelle für die Organisation nicht aktiv ist. */
    public function documentsFor(Customer|Supplier $party, CarbonInterface $from, CarbonInterface $to): ?PartyDocumentList;
}
