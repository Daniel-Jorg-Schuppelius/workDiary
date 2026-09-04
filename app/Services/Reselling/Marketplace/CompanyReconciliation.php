<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CompanyReconciliation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Marketplace;

/**
 * Abgleichsergebnis einer Marketplace-Firma.
 */
final readonly class CompanyReconciliation {
    /**
     * @param  list<PeriodFinding>  $findings
     * @param  list<ExtraLine>  $extras
     * @param  list<string>  $errors
     * @param  list<array{line: InvoiceLine, remaining: float, shared?: bool, microsoft?: bool}>  $lines  alle Positionen der Kontakte im Zeitraum — Diagnose: was der Abgleich gesehen hat; shared = Partnerkontakt ohne Nennung dieser Firma
     */
    public function __construct(
        public ContactMapping $mapping,
        public array $findings,
        public array $extras,
        public array $errors = [],
        public array $lines = [],
    ) {}

    public function company(): MarketplaceCompany {
        return $this->mapping->company;
    }
}
