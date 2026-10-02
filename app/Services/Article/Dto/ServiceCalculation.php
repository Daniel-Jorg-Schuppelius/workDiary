<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServiceCalculation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Article\Dto;

use App\Enums\Article\CostKind;
use CommonToolkit\ValueObjects\{Money, Percentage};

/**
 * Ergebnis der Kalkulation einer Leistungseinheit (MVP-1055): je Kostenart
 * Einzelkosten, Zuschlag und Preisanteil, dazu Summe, Lohnminuten und der
 * Anteil der Arbeits- und Maschinenkosten.
 */
final readonly class ServiceCalculation {
    /**
     * @param  array<string, array{cost: Money, markup: Percentage, price: Money}>  $kinds  Schlüssel = CostKind::value
     */
    public function __construct(
        public array $kinds,
        public Money $cost,
        public Money $price,
        public float $labourMinutes,
        public ?Percentage $labourShare,
    ) {}

    public function priceOf(CostKind $kind): ?Money {
        return $this->kinds[$kind->value]['price'] ?? null;
    }

    /**
     * Schnappschuss für die Belegposition: Zahlen als Dezimalstrings.
     *
     * @return array{kinds: array<string, array{cost: string, markup: string, price: string}>, cost: string, price: string, labour_minutes: float, labour_share: ?string}
     */
    public function toSnapshot(): array {
        $kinds = [];
        foreach ($this->kinds as $key => $row) {
            $kinds[$key] = [
                'cost' => $row['cost']->getAmount(),
                'markup' => $row['markup']->getNumericValue(),
                'price' => $row['price']->getAmount(),
            ];
        }

        return [
            'kinds' => $kinds,
            'cost' => $this->cost->getAmount(),
            'price' => $this->price->getAmount(),
            'labour_minutes' => $this->labourMinutes,
            'labour_share' => $this->labourShare?->getNumericValue(),
        ];
    }
}
