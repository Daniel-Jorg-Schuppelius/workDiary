<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EnteredQuantity.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Article\{Article, ArticleVariant};
use App\Services\Article\UnitConverter;
use App\Support\DecimalQty;
use CommonToolkit\Enums\RoundingMode;
use CommonToolkit\Helper\Data\NumberHelper;
use RuntimeException;

/**
 * Eine in einer Artikeleinheit erfasste Menge (Feature 048, MVP-1584): gebucht
 * wird `base` in der Basiseinheit, Eingabewert und Einheit bleiben als
 * Snapshot an der Bewegung. Der Faktor wird beim Erfassen festgehalten, damit
 * eine spätere Änderung der Einheit die Buchung nicht verschiebt.
 */
final readonly class EnteredQuantity {
    /**
     * @param numeric-string $base
     * @param numeric-string $originalQty
     * @param numeric-string $factor Basiseinheiten je erfasster Einheit
     */
    private function __construct(
        public string $base,
        public string $originalQty,
        public string $originalUnit,
        public string $factor,
    ) {}

    /** Leere Einheit = Basiseinheit des Artikels. */
    public static function of(ArticleVariant $variant, string $qty, ?string $unitCode): self {
        $article = $variant->article;
        if (! $article instanceof Article) {
            throw new RuntimeException('Variante ohne Artikel.');
        }

        $unit = trim((string) $unitCode) !== '' ? trim((string) $unitCode) : (string) $article->base_unit;
        $entered = DecimalQty::positive($qty);
        $converter = app(UnitConverter::class);
        $base = $converter->toBase($article, $entered, $unit);
        // Eine Menge, die in der Basiseinheit auf null rundet, wäre eine Buchung ohne Wirkung.
        if (NumberHelper::comparePrecise($entered, '0', 8) > 0 && NumberHelper::comparePrecise($base, '0', UnitConverter::SCALE) === 0) {
            throw new RuntimeException((string) __('inventory.error.unit_quantity_too_small', ['unit' => $unit]));
        }

        return new self($base, $entered, $unit, $converter->factor($article, $unit));
    }

    /**
     * Anteil der Eingabe für einen Teil der Buchung (Abgang über mehrere Chargen).
     *
     * @param numeric-string $partBase
     * @return numeric-string
     */
    public function originalFor(string $partBase): string {
        if (NumberHelper::comparePrecise(DecimalQty::positive($partBase), $this->base, UnitConverter::SCALE) === 0) {
            return $this->originalQty;
        }

        return NumberHelper::dividePrecise(DecimalQty::positive($partBase), $this->factor, UnitConverter::SCALE, RoundingMode::HalfUp);
    }

    /**
     * Preis je erfasster Einheit → Kosten je Basiseinheit.
     *
     * @return numeric-string
     */
    public function unitCostInBase(string $costPerEnteredUnit): string {
        return NumberHelper::dividePrecise(DecimalQty::positive($costPerEnteredUnit), $this->factor, UnitConverter::SCALE, RoundingMode::HalfUp);
    }
}
