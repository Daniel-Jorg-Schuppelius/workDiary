<?php
/*
 * Created on   : Tue Oct 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RepairLotStockCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Inventory;

use App\Console\Concerns\IteratesOrganizations;
use App\Enums\Inventory\{StockLotStatus, ValuationMethod};
use App\Models\Article\ArticleVariant;
use App\Models\Inventory\{StockLot, StockValuationLayer};
use App\Models\Platform\Organization;
use App\Services\Inventory\{InventoryLedger, LotService, LotStockReader, ValuationMethodResolver};
use CommonToolkit\ValueObjects\Decimal;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Altbestand der Chargen (Feature 048, E8.2): Abgänge trugen bis 2026-10 keine
 * Charge, der Buchsaldo einer Charge blieb über dem, was von ihr noch da ist.
 * Unter FIFO/FEFO zeigt die Restschicht den echten Rest; liegt der Buchsaldo
 * darüber, bucht `--apply` die Differenz je Topf nach „ohne Charge“ um — der
 * physische Bestand bleibt gleich. Unter gleitendem Durchschnitt sinken die
 * Schichten nie, dort bleibt es bei der Meldung; gesperrte Chargen erst nach
 * der Freigabe (die Sperrbuchung stünde sonst über dem Chargensaldo).
 */
class RepairLotStockCommand extends Command {
    use IteratesOrganizations;

    protected $signature = 'inventory:lots:repair {--apply : Korrekturen buchen (sonst Probelauf)} ' . self::ORGANIZATION_OPTION;

    protected $description = 'Gleicht überhöhte Chargensalden im Lagerbuch mit den Bewertungsschichten ab (FIFO/FEFO). Ohne --apply nur Probelauf.';

    public function handle(LotStockReader $lotStock, LotService $lots, ValuationMethodResolver $methods): int {
        $apply = (bool) $this->option('apply');
        $rows = [];
        $failures = $this->forEachOrganization(function (Organization $organization) use ($lotStock, $lots, $methods, $apply, &$rows): void {
            StockLot::query()
                ->where('organization_id', $organization->id)
                ->where('status', '!=', StockLotStatus::Merged->value)
                ->with('variant.article')
                ->chunkById(200, function (Collection $chunk) use ($organization, $lotStock, $lots, $methods, $apply, &$rows): void {
                    $book = $lotStock->balancesOf($chunk);
                    $rest = $this->layerRest($chunk->modelKeys());
                    foreach ($chunk as $lot) {
                        /** @var StockLot $lot */
                        $balance = Decimal::of($book[$lot->id], InventoryLedger::SCALE);
                        $layers = $rest[$lot->id] ?? Decimal::zero(InventoryLedger::SCALE);
                        $excess = $balance->minus($layers);
                        if ($excess->isZero()) {
                            continue;
                        }
                        $rows[] = [
                            $organization->name,
                            $lot->lot_no,
                            $balance->getValue(),
                            $layers->getValue(),
                            $excess->getValue(),
                            $this->repair($lot, $excess, $organization, $lots, $methods, $apply),
                        ];
                    }
                });
        });

        if ($rows === []) {
            $this->info('Keine Abweichung zwischen Buchsaldo und Restschicht.');
        } else {
            $this->table(['Organisation', 'Charge', 'Buchsaldo', 'Schichtrest', 'Differenz', 'Ergebnis'], $rows);
        }
        if (! $apply) {
            $this->line('Probelauf — gebucht wird erst mit --apply.');
        }

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function repair(StockLot $lot, Decimal $excess, Organization $organization, LotService $lots, ValuationMethodResolver $methods, bool $apply): string {
        $variant = $lot->variant;
        if (! $excess->isPositive()) {
            return 'Buchsaldo unter Restschicht — keine Korrektur';
        }
        if (! $variant instanceof ArticleVariant || $methods->methodForVariant($variant, $organization) === ValuationMethod::MovingAverage) {
            return 'gleitender Durchschnitt — nur Meldung';
        }
        if ($lot->status === StockLotStatus::Blocked) {
            return 'gesperrt — erst freigeben';
        }
        if (! $apply) {
            return 'würde nach „ohne Charge“ umgebucht';
        }
        $lots->detach($lot, $excess->getValue());

        return 'nach „ohne Charge“ umgebucht';
    }

    /**
     * Restmenge der Bewertungsschichten je Charge.
     *
     * @param  array<int, int|string>  $lotIds
     * @return array<int, Decimal>
     */
    private function layerRest(array $lotIds): array {
        $rest = [];
        $rows = StockValuationLayer::query()
            ->whereIn('stock_lot_id', $lotIds)
            ->where('qty_remaining', '>', 0)
            ->toBase()
            ->get(['stock_lot_id', 'qty_remaining']);
        foreach ($rows as $row) {
            $qty = Decimal::of((string) $row->qty_remaining, InventoryLedger::SCALE);
            $rest[(int) $row->stock_lot_id] = isset($rest[(int) $row->stock_lot_id]) ? $rest[(int) $row->stock_lot_id]->plus($qty) : $qty;
        }

        return $rest;
    }
}
