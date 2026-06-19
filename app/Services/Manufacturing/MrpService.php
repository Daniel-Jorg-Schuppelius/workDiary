<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MrpService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\{Article, ArticleVariant, ProcedureTemplateVersion, Warehouse};
use App\Services\Inventory\InventoryLedger;

/**
 * Mehrstufige Materialbedarfsplanung (Feature 047/048, E7). Löst die Stückliste
 * eines Erzeugnisses über Halbfabrikate hinweg auf und ermittelt je Komponente
 * den abhängigen Sekundärbedarf. Eigenfertigungskomponenten (manufacturable +
 * eigene Stückliste) werden weiter aufgelöst und ergeben Fertigungsvorschläge
 * (source=make); Zukauf-Komponenten sind Blätter (source=buy). Optional wird der
 * Bruttobedarf gegen den Bestand eines Lagers zum Nettobedarf verrechnet; die
 * Weiterauflösung läuft nur über die Nettomenge.
 *
 * @phpstan-type MrpLine array{article_id: int, variant_id: int|null, level: int, gross: numeric-string, available: numeric-string, net: numeric-string, source: 'make'|'buy'}
 */
class MrpService {
    public const SCALE = 4;
    public const MAX_DEPTH = 20;

    public function __construct(
        private readonly BomResolver $bom,
        private readonly MaterialDemandCalculator $calculator,
        private readonly InventoryLedger $ledger,
    ) {}

    /**
     * @return list<MrpLine>
     */
    public function explode(Article $article, ?ArticleVariant $variant, string $qty, ?Warehouse $warehouse = null): array {
        $lines = [];
        $this->expand($article, $variant, $this->numeric($qty), $warehouse, 1, [(int) $article->id], $lines);

        return $lines;
    }

    /**
     * @param  list<int>  $path
     * @param  list<MrpLine>  $lines
     */
    private function expand(Article $article, ?ArticleVariant $variant, string $qty, ?Warehouse $warehouse, int $level, array $path, array &$lines): void {
        if ($level > self::MAX_DEPTH) {
            return;
        }
        $version = $article->defaultProcedureVersion;
        if (! $version instanceof ProcedureTemplateVersion) {
            return; // kein Arbeitsplan/Stückliste → keine Auflösung
        }

        foreach ($this->calculator->calculate($this->bom->resolve($version, $variant), $qty) as $row) {
            $requirement = $row['requirement'];
            if ($requirement->is_tool) {
                continue; // Werkzeuge sind kein Materialbedarf
            }

            $component = Article::query()->find($requirement->article_id);
            if (! $component instanceof Article) {
                continue;
            }
            $componentVariant = $requirement->article_variant_id !== null
                ? ArticleVariant::query()->find($requirement->article_variant_id)
                : $this->defaultVariant($component);

            $gross = $row['demand'];
            $available = $warehouse instanceof Warehouse && $componentVariant instanceof ArticleVariant
                ? $this->ledger->available($componentVariant, $warehouse)
                : '0';
            $net = bccomp($gross, $available, self::SCALE) > 0 ? bcsub($gross, $available, self::SCALE) : '0.0000';

            $componentVersion = $component->defaultProcedureVersion;
            $make = (bool) $component->manufacturable && $componentVersion instanceof ProcedureTemplateVersion;

            $lines[] = [
                'article_id' => (int) $component->id,
                'variant_id' => $componentVariant instanceof ArticleVariant ? (int) $componentVariant->id : null,
                'level' => $level,
                'gross' => $gross,
                'available' => bcadd($available, '0', self::SCALE),
                'net' => $net,
                'source' => $make ? 'make' : 'buy',
            ];

            // Eigenfertigung: Nettomenge weiter auflösen (mit Zyklusschutz).
            if ($make && bccomp($net, '0', self::SCALE) > 0 && ! in_array((int) $component->id, $path, true)) {
                $this->expand($component, $componentVariant, $net, $warehouse, $level + 1, array_merge($path, [(int) $component->id]), $lines);
            }
        }
    }

    private function defaultVariant(Article $article): ?ArticleVariant {
        return ArticleVariant::query()
            ->where('article_id', $article->id)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }

    /** @return numeric-string */
    private function numeric(string $value): string {
        $value = str_replace(',', '.', trim($value));
        if ($value === '' || ! is_numeric($value)) {
            return '0';
        }

        return bccomp($value, '0', self::SCALE) < 0 ? '0' : bcadd($value, '0', self::SCALE);
    }
}
