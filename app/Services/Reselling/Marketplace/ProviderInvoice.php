<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProviderInvoice.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Marketplace;

use Carbon\CarbonImmutable;

/**
 * Eine Anbieterrechnung oder -gutschrift (Feature 152, MVP-762), aus dem PDF
 * gelesen: Kopf plus Positionen mit Vertrag, Endkunde und Laufzeit.
 */
final class ProviderInvoice {
    /**
     * @param  list<ProviderInvoiceLine>  $lines
     * @param  list<string>  $issues
     */
    public function __construct(
        public string $number,
        public ?CarbonImmutable $date,
        public bool $credit,
        public ?string $customerNumber,
        public array $lines,
        public ?float $netTotal,
        public array $issues = [],
    ) {}

    /** Toleranz zwischen Positionssumme und Nettobetrag des Belegs. */
    public const TOTAL_TOLERANCE = 0.01;

    /** Summe der Positionen (Gutschrift negativ). */
    public function linesTotal(): float {
        return round(array_sum(array_map(static fn(ProviderInvoiceLine $l): float => $l->total, $this->lines)), 2);
    }

    /**
     * Positionssumme trifft den Nettobetrag (|Δ| ≤ 0,01). Ohne Nettobetrag im
     * Text gilt der Beleg als konsistent — nur eine erkannte Abweichung zählt
     * (Review 2026-09-10, B17: Seite 2 anders zerlegt → Positionen fehlen).
     */
    public function isConsistent(): bool {
        return $this->netTotal === null || abs($this->linesTotal() - $this->netTotal) <= self::TOTAL_TOLERANCE + 1e-9;
    }

    /** Befund zur Summenabweichung, null wenn konsistent. */
    public function consistencyIssue(): ?string {
        if ($this->isConsistent()) {
            return null;
        }

        return (string) __('resale_import.invoice.total_mismatch', [
            'lines' => number_format($this->linesTotal(), 2, ',', '.'),
            'net' => number_format((float) $this->netTotal, 2, ',', '.'),
        ]);
    }
}
