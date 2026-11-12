<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UnitConverter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Article;

use App\Models\Article\{Article, ArticleUnit};
use CommonToolkit\Enums\RoundingMode;
use CommonToolkit\Helper\Data\NumberHelper;
use RuntimeException;

/**
 * Rechnet Mengen zwischen einer artikelbezogenen Einheit und der Basiseinheit
 * um (Feature 048, MVP-060). Rein dezimal, nie Fließkomma; gerundet wird
 * kaufmännisch auf die Skala der Bestandsführung.
 * Dimensionswechsel (z. B. Liter↔Kilogramm) sind nur über einen ausdrücklich
 * gepflegten artikelbezogenen Faktor möglich — sonst gibt es keine Einheit und
 * die Umrechnung schlägt fehl.
 */
class UnitConverter {
    /** Nachkommastellen der internen Bestandsführung (Basiseinheit). */
    public const int SCALE = 4;

    /**
     * Rechnet eine Menge in der angegebenen Einheit in die Basiseinheit um.
     *
     * @return numeric-string
     */
    public function toBase(Article $article, string $quantity, string $unitCode): string {
        $factor = $this->factor($article, $unitCode);

        return NumberHelper::multiplyPrecise(NumberHelper::normalizeDecimalString($quantity), $factor, self::SCALE, RoundingMode::HalfUp);
    }

    /**
     * Faktor der Einheit zur Basiseinheit; Basiseinheit selbst = 1.
     *
     * @return numeric-string
     */
    public function factor(Article $article, string $unitCode): string {
        if ($unitCode === $article->base_unit) {
            return '1';
        }

        /** @var ArticleUnit|null $unit */
        $unit = $article->units()->where('code', $unitCode)->where('active', true)->first();
        if ($unit === null) {
            throw new RuntimeException((string) __('inventory.error.unit_not_maintained', ['unit' => $unitCode, 'article' => (string) $article->name]));
        }

        $factor = (string) $unit->factor_to_base;
        if (! is_numeric($factor) || bccomp($factor, '0', 8) <= 0) {
            throw new RuntimeException((string) __('inventory.error.unit_factor_invalid', ['unit' => $unitCode, 'article' => (string) $article->name]));
        }

        return $factor;
    }
}
