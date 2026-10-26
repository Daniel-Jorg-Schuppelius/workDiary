<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PlanLine.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Plan;

use App\Services\Reselling\Register\LicenseMonths;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;

/**
 * Position einer Serienvorlage (Feature 185, MVP-1409), anbieterneutral wie
 * {@see \App\Services\Reselling\Mirror\MirrorLine}: Artikel, Menge, Einheit
 * und Stückpreis — daraus die geplanten Lizenzen je Ausführung.
 */
final readonly class PlanLine {
    public function __construct(
        public int $position,
        public ?string $articleKey,
        public ?string $articleName,
        /** Abo-Produkt laut Einstufung ({@see \App\Services\Reselling\Register\LicenseArticleClassifier}). */
        public bool $articleIsLicence,
        public string $name,
        public ?string $description,
        public float $quantity,
        public ?string $unitName,
        public Money $unitNet,
        public CurrencyCode $currency,
    ) {}

    /** Anzeigename: Artikel, sonst Positionsname. */
    public function label(): string {
        $article = trim((string) $this->articleName);

        return $article !== '' ? $article : $this->name;
    }

    public function text(): string {
        return trim($this->name . ' ' . (string) $this->description);
    }

    /**
     * Geplante Lizenzen je Ausführung: Monatsposition „12 Monat" bei jährlicher
     * Ausführung = eine Lizenz, „60 Monat" = fünf; Jahr/Stück = Menge.
     */
    public function licences(int $intervalMonths): float {
        if (LicenseMonths::isMonthUnit($this->unitName)) {
            if ($intervalMonths > 0 && $this->quantity >= $intervalMonths && abs(fmod($this->quantity, $intervalMonths)) < 0.001) {
                return $this->quantity / $intervalMonths;
            }

            return $this->quantity;
        }

        return $this->quantity;
    }
}
