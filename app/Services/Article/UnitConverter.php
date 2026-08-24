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

use App\Models\{Article, ArticleUnit};
use CommonToolkit\Helper\Data\NumberHelper;
use RuntimeException;

/**
 * Rechnet Mengen zwischen einer artikelbezogenen Einheit und der Basiseinheit
 * um (Feature 048, MVP-060). Rein dezimal (bcmath), nie Fließkomma.
 * Dimensionswechsel (z. B. Liter↔Kilogramm) sind nur über einen ausdrücklich
 * gepflegten artikelbezogenen Faktor möglich — sonst gibt es keine Einheit und
 * die Umrechnung schlägt fehl.
 */
class UnitConverter {
    /** Nachkommastellen der internen Bestandsführung (Basiseinheit). */
    public const SCALE = 4;

    /** Rechnet eine Menge in der angegebenen Einheit in die Basiseinheit um. */
    public function toBase(Article $article, string $quantity, string $unitCode): string {
        $factor = $this->factor($article, $unitCode);

        return $this->round(bcmul(NumberHelper::normalizeDecimalString($quantity), $factor, self::SCALE + 4));
    }

    /** Rechnet eine Menge aus der Basiseinheit in die angegebene Einheit um. */
    public function fromBase(Article $article, string $quantity, string $unitCode): string {
        $factor = $this->factor($article, $unitCode);
        if (bccomp($factor, '0', 8) === 0) {
            throw new RuntimeException('Einheiten-Faktor 0 ist ungültig.');
        }

        return $this->round(bcdiv(NumberHelper::normalizeDecimalString($quantity), $factor, self::SCALE + 4));
    }

    /**
     * Faktor der Einheit zur Basiseinheit; Basiseinheit selbst = 1.
     *
     * @return numeric-string
     */
    private function factor(Article $article, string $unitCode): string {
        if ($unitCode === $article->base_unit) {
            return '1';
        }

        /** @var ArticleUnit|null $unit */
        $unit = $article->units()->where('code', $unitCode)->where('active', true)->first();
        if ($unit === null) {
            throw new RuntimeException(sprintf(
                'Keine gepflegte Umrechnung für Einheit "%s" am Artikel %d.',
                $unitCode,
                (int) $article->id,
            ));
        }

        $factor = (string) $unit->factor_to_base;

        return is_numeric($factor) ? $factor : '1';
    }

    /**
     * @param  numeric-string  $value
     * @return numeric-string
     */
    private function round(string $value): string {
        // bcadd mit Skala schneidet auf SCALE Stellen ab (die Werte wurden
        // zuvor breiter gerechnet).
        return bcadd($value, '0', self::SCALE);
    }
}
