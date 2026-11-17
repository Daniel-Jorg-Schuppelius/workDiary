<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatevTaxCheckResult.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Datev\TaxCheck;

/**
 * Ergebnis der Steuerprüfung vor dem DATEV-Export (Feature 207, MVP-1661):
 * Fehler und Hinweise samt den bereits begründet freigegebenen Schlüsseln.
 * Gesperrt ist der Export, solange ein Fehler weder berichtigt (dann fehlt er
 * hier) noch freigegeben ist.
 */
final readonly class DatevTaxCheckResult {
    /**
     * @param  list<DatevTaxCheckFinding>  $findings
     * @param  list<string>  $releasedKeys
     */
    public function __construct(
        public array $findings,
        public array $releasedKeys = [],
    ) {}

    /** @return list<DatevTaxCheckFinding> */
    public function errors(): array {
        return array_values(array_filter($this->findings, static fn (DatevTaxCheckFinding $finding): bool => $finding->blocking));
    }

    /** @return list<DatevTaxCheckFinding> */
    public function hints(): array {
        return array_values(array_filter($this->findings, static fn (DatevTaxCheckFinding $finding): bool => ! $finding->blocking));
    }

    /** @return list<DatevTaxCheckFinding> Fehler ohne Freigabe */
    public function openErrors(): array {
        return array_values(array_filter($this->errors(), fn (DatevTaxCheckFinding $finding): bool => ! $this->isReleased($finding)));
    }

    public function isReleased(DatevTaxCheckFinding $finding): bool {
        return $finding->blocking && in_array($finding->key, $this->releasedKeys, true);
    }

    public function isBlocked(): bool {
        return $this->openErrors() !== [];
    }

    public function isEmpty(): bool {
        return $this->findings === [];
    }
}
