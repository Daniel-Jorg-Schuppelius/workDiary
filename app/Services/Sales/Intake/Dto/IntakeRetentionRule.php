<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntakeRetentionRule.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sales\Intake\Dto;

use Carbon\{CarbonImmutable, CarbonInterface};

/**
 * Aufbewahrung eines Prüfpakets (Feature 200, MVP-1643): Jahre ab dem
 * Schluss des Kalenderjahres, in dem der Anker liegt — nach § 8 Abs. 4
 * Satz 2 und 3 GwG das Ende der Geschäftsbeziehung bzw. das Jahr, in dem die
 * Angabe festgestellt wurde (abgelehnte und abgebrochene Aufnahmen).
 */
final readonly class IntakeRetentionRule {
    public const int MAX_YEARS = 30;

    public function __construct(
        public int $years,
        public string $basis,
    ) {}

    /** Profildaten oder eingefrorene Form lesen; ohne Jahre keine Regel. */
    public static function fromArray(mixed $raw): ?self {
        if (! is_array($raw) || ! is_numeric($raw['years'] ?? null)) {
            return null;
        }
        $years = (int) $raw['years'];
        if ($years < 1 || $years > self::MAX_YEARS) {
            return null;
        }

        return new self($years, trim((string) ($raw['basis'] ?? '')));
    }

    /** @return array{years: int, basis: string} */
    public function toArray(): array {
        return ['years' => $this->years, 'basis' => $this->basis];
    }

    /**
     * Letzter Tag der Frist: Schluss des Ankerjahres plus Jahre. Anker
     * 15.03.2026, fünf Jahre → Frist beginnt mit Ablauf des 31.12.2026 und
     * endet am 31.12.2031.
     */
    public function until(CarbonInterface $anchor): CarbonImmutable {
        return CarbonImmutable::instance($anchor)->endOfYear()->addYears($this->years)->startOfDay();
    }
}
