<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProductCarbonFootprintService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Article\{Article, ArticleVariant};
use CommonToolkit\Helper\Data\NumberHelper;

/**
 * Product Carbon Footprint (MVP-960) je Stück: Zukaufteile der aufgelösten
 * Stückliste × Emissionsfaktor des Artikels, dazu die Prozessemissionen der
 * Eigenfertigungsstufen. Cradle-to-gate, ohne Nutzung und Entsorgung; fehlende
 * Faktoren werden ausgewiesen, nicht geschätzt.
 *
 * @phpstan-type FootprintLine array{article: Article, level: int, quantity: numeric-string, source: 'make'|'buy', factor: ?numeric-string, kg: ?numeric-string}
 */
class ProductCarbonFootprintService {
    private const SCALE = 4;

    public function __construct(private readonly MrpService $mrp) {}

    /**
     * @return array{total_kg: numeric-string, material_kg: numeric-string, process_kg: numeric-string, lines: list<FootprintLine>, missing: list<Article>, complete: bool}
     */
    public function footprint(Article $article, ?ArticleVariant $variant = null): array {
        $material = '0';
        $process = $this->perUnit($article->pcf_process_kg, '1') ?? '0';
        $lines = [];
        $missing = [];

        $exploded = $this->mrp->explode($article, $variant, '1');
        if ($exploded === []) {
            // Ohne Stückliste gilt der Artikel selbst als Zukaufteil.
            $kg = $this->perUnit($article->pcf_factor_kg, '1');
            if ($kg === null) {
                $missing[] = $article;
            }
            $material = $kg ?? '0';
        }
        $articles = Article::query()->whereIn('id', array_column($exploded, 'article_id'))->get()->keyBy('id');
        foreach ($exploded as $row) {
            $component = $articles->get($row['article_id']);
            if (! $component instanceof Article) {
                continue;
            }
            $factor = $row['source'] === 'buy' ? $component->pcf_factor_kg : $component->pcf_process_kg;
            $kg = $this->perUnit($factor, $row['gross']);
            if ($row['source'] === 'buy') {
                if ($kg === null) {
                    $missing[] = $component;
                } else {
                    $material = NumberHelper::addPrecise($material, $kg, self::SCALE);
                }
            } elseif ($kg !== null) {
                $process = NumberHelper::addPrecise($process, $kg, self::SCALE);
            }
            $lines[] = ['article' => $component, 'level' => $row['level'], 'quantity' => $row['gross'], 'source' => $row['source'], 'factor' => $factor, 'kg' => $kg];
        }

        return [
            'total_kg' => NumberHelper::addPrecise($material, $process, self::SCALE),
            'material_kg' => NumberHelper::roundPrecise($material, self::SCALE),
            'process_kg' => NumberHelper::roundPrecise($process, self::SCALE),
            'lines' => $lines,
            'missing' => array_values(array_unique($missing, SORT_REGULAR)),
            'complete' => $missing === [],
        ];
    }

    /**
     * @param  numeric-string|null  $factor
     * @param  numeric-string  $quantity
     * @return numeric-string|null
     */
    private function perUnit(?string $factor, string $quantity): ?string {
        return $factor === null ? null : NumberHelper::multiplyPrecise($factor, $quantity, self::SCALE);
    }
}
