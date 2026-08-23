<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VatReturnPeriod.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting\Filing;

use App\Enums\Finance\VatFilingInterval;
use Carbon\CarbonImmutable;

/**
 * Ein Voranmeldungszeitraum (Feature 125, MVP-685).
 *
 * Der Schlüssel (`2026-M03`, `2026-Q1`, `2026-J`) ist die einzige Kennung, mit
 * der Auswertung, Frist und Erledigung aufeinander zeigen. Ein freier
 * Von-Bis-Zeitraum könnte über Periodengrenzen laufen — und das Ergebnis sähe
 * aus wie eine Voranmeldung, wäre aber keine.
 */
final class VatReturnPeriod {
    public function __construct(
        public readonly string $key,
        public readonly VatFilingInterval $interval,
        public readonly int $year,
        /** Laufende Nummer im Jahr: Monat 1–12, Quartal 1–4, Jahr 1. */
        public readonly int $ordinal,
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
    ) {}

    /** Baut die Periode aus Intervall, Jahr und laufender Nummer. */
    public static function make(VatFilingInterval $interval, int $year, int $ordinal): self {
        [$key, $from, $to] = match ($interval) {
            VatFilingInterval::Monthly => [
                sprintf('%d-M%02d', $year, $ordinal),
                CarbonImmutable::parse(sprintf('%04d-%02d-%02d', $year, $ordinal, 1)),
                CarbonImmutable::parse(sprintf('%04d-%02d-%02d', $year, $ordinal, 1))->endOfMonth(),
            ],
            VatFilingInterval::Quarterly => [
                sprintf('%d-Q%d', $year, $ordinal),
                CarbonImmutable::parse(sprintf('%04d-%02d-01', $year, ($ordinal - 1) * 3 + 1)),
                CarbonImmutable::parse(sprintf('%04d-%02d-01', $year, ($ordinal - 1) * 3 + 1))->addMonths(2)->endOfMonth(),
            ],
            default => [
                sprintf('%d-J', $year),
                CarbonImmutable::parse(sprintf('%04d-%02d-%02d', $year, 1, 1)),
                CarbonImmutable::parse(sprintf('%04d-%02d-%02d', $year, 12, 31)),
            ],
        };

        return new self($key, $interval, $year, $ordinal, $from->startOfDay(), $to->startOfDay());
    }

    /** Ist dies die letzte Periode des Kalenderjahres? Dort wird angerechnet. */
    public function isLastOfYear(): bool {
        return $this->ordinal === $this->interval->periodsPerYear();
    }

    public function label(): string {
        return match ($this->interval) {
            VatFilingInterval::Monthly => $this->from->translatedFormat('F Y'),
            VatFilingInterval::Quarterly => sprintf('Q%d %d', $this->ordinal, $this->year),
            default => (string) $this->year,
        };
    }

    public function contains(CarbonImmutable $date): bool {
        return $date->betweenIncluded($this->from, $this->to);
    }
}
