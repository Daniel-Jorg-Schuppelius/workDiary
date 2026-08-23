<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingPreflightReport.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting\Preflight;

/**
 * Gesamtergebnis des Einrichtungs-Preflights (Feature 125, MVP-671).
 *
 * Die Aktivierung hängt allein an {@see self::isReady()} — ein Override wäre
 * hier fehl am Platz: Wer ohne Geschäftsjahr oder mit fremdgeführtem Zeitraum
 * lokal zu buchen beginnt, erzeugt genau den stillen Doppelbetrieb, den das
 * Modul verhindern soll.
 */
final class AccountingPreflightReport {
    /** @param list<AccountingPreflightCheck> $checks */
    public function __construct(public readonly array $checks) {}

    /** @return list<AccountingPreflightCheck> */
    public function blockers(): array {
        return array_values(array_filter($this->checks, fn (AccountingPreflightCheck $c): bool => $c->blocking && ! $c->passed));
    }

    /** @return list<AccountingPreflightCheck> */
    public function warnings(): array {
        return array_values(array_filter($this->checks, fn (AccountingPreflightCheck $c): bool => ! $c->blocking && ! $c->passed));
    }

    public function isReady(): bool {
        return $this->blockers() === [];
    }

    /**
     * Nachweis-Form für `accounting_profiles.preflight`.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array {
        return [
            'ready' => $this->isReady(),
            'checked_at' => now()->toIso8601String(),
            'checks' => array_map(fn (AccountingPreflightCheck $c): array => $c->toArray(), $this->checks),
        ];
    }
}
