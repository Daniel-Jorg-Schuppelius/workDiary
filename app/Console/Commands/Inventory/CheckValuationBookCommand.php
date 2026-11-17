<?php
/*
 * Created on   : Tue Nov 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CheckValuationBookCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Inventory;

use App\Console\Concerns\IteratesModuleOrganizations;
use App\Enums\Inventory\{OwnershipType, StockState, ValuationMethod};
use App\Models\Article\ArticleVariant;
use App\Models\Inventory\{StockMovement, StockValuation, StockValuationLayer, Warehouse};
use App\Models\Platform\Organization;
use App\Services\Inventory\{InventoryLedger, InventoryValuationManager, ManualStockReceipt, ValuationMethodResolver};
use CommonToolkit\Helper\Data\NumberHelper;
use CommonToolkit\ValueObjects\Decimal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Abgleich des Bewertungsbuchs (Feature 048, MVP-1736): je Variante und Lager
 * der physische Eigenbestand des Lagerbuchs gegen das Buch des wirksamen
 * Verfahrens — Durchschnitt die geführte Menge, FIFO/FEFO die Restmenge der
 * Schichten. Unbewertete Zugänge früherer Stände (manueller Eingang vor
 * MVP-1709, Korrekturen vor MVP-1727, Chargen-Eingang beim Durchschnitt vor
 * MVP-1735) ließen das Buch unter dem Bestand. `--align` trägt die fehlende
 * Menge ohne Lagerbewegung nach, zur Preisfolge des manuellen Eingangs
 * (geschätzt), und hält das im Audit des Lagers fest; ein Buch über dem
 * Bestand wird nur gemeldet. Idempotent: ein angeglichenes Buch hat keine
 * Lücke mehr.
 */
final class CheckValuationBookCommand extends Command {
    use IteratesModuleOrganizations;

    private const int SCALE = 4;

    protected $signature = 'inventory:valuation-check
        {--align : Fehlende Menge zum geschätzten Stückwert ins Bewertungsbuch nachtragen}
        {--dry-run : Mit --align nur anzeigen, was nachgetragen würde}
        ' . self::ORGANIZATION_OPTION;

    protected $description = 'Vergleicht Bewertungsbuch und physischen Eigenbestand je Variante und Lager; --align trägt fehlende Menge geschätzt nach (MVP-1736)';

    public function handle(InventoryLedger $ledger, InventoryValuationManager $valuation, ValuationMethodResolver $methods, ManualStockReceipt $receipts): int {
        $align = (bool) $this->option('align');
        $dryRun = (bool) $this->option('dry-run');
        $gaps = 0;

        $failures = $this->forEachOrganization(function (Organization $organization) use ($ledger, $valuation, $methods, $receipts, $align, $dryRun, &$gaps): void {
            $rows = [];
            foreach ($this->deviations($organization, $methods) as [$variant, $warehouse, $method, $physical, $booked]) {
                $difference = $physical->minus($booked);
                $result = 'Buch über Bestand — nur Meldung';
                if ($difference->isPositive()) {
                    $gaps++;
                    $result = match (true) {
                        ! $align => 'fehlt im Buch — mit --align nachtragen',
                        $dryRun => 'würde geschätzt nachgetragen',
                        default => $this->align($organization, $variant, $warehouse, $ledger, $valuation, $receipts),
                    };
                }
                $rows[] = ['#' . $variant->id, (string) $variant->sku, '#' . $warehouse->id, $method->value, $physical->getValue(), $booked->getValue(), $difference->getValue(), $result];
            }

            if ($rows === []) {
                $this->line(sprintf('Organisation #%d: Bewertungsbuch und Eigenbestand stimmen überein.', $organization->id));

                return;
            }
            $this->line(sprintf('Organisation #%d: %d Abweichung(en).', $organization->id, count($rows)));
            $this->table(['Variante', 'SKU', 'Lager', 'Verfahren', 'Eigenbestand', 'Bewertungsbuch', 'Differenz', 'Ergebnis'], $rows);
        });

        if ($gaps > 0) {
            $this->warn('Hinweis: Nachgetragene Mengen sind geschätzt bewertet — Einkaufspreis der Variante, sonst Durchschnitt des Lagers, sonst 0; kein belegter Einstandswert.');
            if (! $align || $dryRun) {
                $this->line('Probelauf — nachgetragen wird erst mit --align (ohne --dry-run).');
            }
        }

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Bestand vor Buch gesperrt neu lesen (Sperrfolge wie Abgang und Umlagerung), dann das Buch des Verfahrens anheben;
     * eine Wiederholung findet keine Lücke mehr.
     */
    private function align(Organization $organization, ArticleVariant $variant, Warehouse $warehouse, InventoryLedger $ledger, InventoryValuationManager $valuation, ManualStockReceipt $receipts): string {
        $added = DB::transaction(function () use ($organization, $variant, $warehouse, $ledger, $valuation, $receipts): string {
            $physical = $ledger->balance($variant, $warehouse, StockState::Physical, OwnershipType::Own, forUpdate: true);
            $strategy = $valuation->forVariant($variant, $organization);
            $unitCost = $receipts->defaultUnitCost($strategy, $variant, $warehouse, $organization);
            $added = $strategy->raiseBook($variant, $warehouse, $physical, $unitCost);
            if (NumberHelper::isPositivePrecise($added, self::SCALE)) {
                $warehouse->audit('inventory.valuationAligned', [
                    'article_variant_id' => $variant->id,
                    'method' => $strategy->method()->value,
                    'qty' => $added,
                    'unit_cost' => $unitCost->withScale(self::SCALE)->getAmount(),
                    'currency' => $unitCost->getCurrency()->value,
                    'estimated' => true,
                ]);
            }

            return $added;
        });

        return NumberHelper::isPositivePrecise($added, self::SCALE) ? "nachgetragen: {$added} (geschätzt)" : 'inzwischen ausgeglichen';
    }

    /**
     * Varianten und Lager, deren Eigenbestand vom Buch des wirksamen Verfahrens abweicht (Verfahrenswahl wie
     * {@see InventoryValuationManager::forVariant()}).
     *
     * @return list<array{0: ArticleVariant, 1: Warehouse, 2: ValuationMethod, 3: Decimal, 4: Decimal}>
     */
    private function deviations(Organization $organization, ValuationMethodResolver $methods): array {
        $physical = $this->physicalStock($organization);
        $average = $this->sums(StockValuation::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->toBase()->get(['article_variant_id', 'warehouse_id', 'qty_on_hand as qty']));
        $layered = $this->sums(StockValuationLayer::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->toBase()->get(['article_variant_id', 'warehouse_id', 'qty_remaining as qty']));

        $keys = array_unique([...array_keys($physical), ...array_keys($average), ...array_keys($layered)]);
        sort($keys, SORT_NATURAL);
        if ($keys === []) {
            return [];
        }
        $variantIds = array_values(array_unique(array_map(static fn (string $key): int => (int) explode(':', $key)[0], $keys)));
        $variants = ArticleVariant::query()->withoutGlobalScopes()
            ->with(['article' => static fn ($query) => $query->withoutGlobalScopes()])
            ->whereIn('id', $variantIds)
            ->get()
            ->keyBy('id');
        $warehouses = Warehouse::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->get()->keyBy('id');

        $out = [];
        $zero = Decimal::zero(self::SCALE);
        foreach ($keys as $key) {
            [$variantId, $warehouseId] = array_map(intval(...), explode(':', $key));
            $variant = $variants->get($variantId);
            $warehouse = $warehouses->get($warehouseId);
            if (! $variant instanceof ArticleVariant || ! $warehouse instanceof Warehouse) {
                continue;
            }
            $method = $methods->methodForVariant($variant, $organization);
            $booked = ($method === ValuationMethod::MovingAverage ? $average : $layered)[$key] ?? $zero;
            $stock = $physical[$key] ?? $zero;
            if (! $stock->minus($booked)->isZero()) {
                $out[] = [$variant, $warehouse, $method, $stock, $booked];
            }
        }

        return $out;
    }

    /**
     * Physischer Eigenbestand je Variante und Lager aus dem Lagerbuch, exakt summiert (SUM() rechnet in SQLite als Float).
     *
     * @return array<string, Decimal> "variante:lager" → Menge
     */
    private function physicalStock(Organization $organization): array {
        $sums = [];
        StockMovement::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('stock_state', StockState::Physical->value)
            ->where('ownership_type', OwnershipType::Own->value)
            ->toBase()
            ->select(['id', 'article_variant_id', 'warehouse_id', 'qty_base as qty'])
            ->lazyById(2000)
            ->each(function (\stdClass $row) use (&$sums): void {
                $this->add($sums, $row);
            });

        return $sums;
    }

    /**
     * @param  iterable<\stdClass>  $rows
     * @return array<string, Decimal>
     */
    private function sums(iterable $rows): array {
        $sums = [];
        foreach ($rows as $row) {
            $this->add($sums, $row);
        }

        return $sums;
    }

    /** @param array<string, Decimal> $sums */
    private function add(array &$sums, \stdClass $row): void {
        $key = (int) $row->article_variant_id . ':' . (int) $row->warehouse_id;
        $qty = Decimal::of(NumberHelper::normalizeDecimalString((string) $row->qty), self::SCALE);
        $sums[$key] = isset($sums[$key]) ? $sums[$key]->plus($qty) : $qty;
    }
}
