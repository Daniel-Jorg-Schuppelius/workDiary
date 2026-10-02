<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BoqPricingService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Gaeb;

use App\Enums\Article\CostKind;
use App\Enums\Gaeb\BoqItemStatus;
use App\Models\Gaeb\{BillOfQuantity, BoqItem};
use App\Services\Billing\DocumentTotalsCalculator;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\Helper\Data\NumberHelper;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Bepreisen eines Leistungsverzeichnisses für die Angebotsabgabe (MVP-1056):
 * Einheitspreis je Position, wahlweise aus der EP-Aufgliederung (Summe der
 * Anteile), oder „nicht angeboten". Der Gesamtbetrag rechnet der
 * {@see DocumentTotalsCalculator}. Nach der Abgabe (Status „angeboten“) ist das
 * LV gesperrt — eine Änderung ist ein neuer Stand.
 */
final class BoqPricingService {
    /** Status, in denen ein LV noch bepreist werden darf. */
    public const EDITABLE = [BoqItemStatus::Draft, BoqItemStatus::Imported];

    /**
     * Kostenart je EP-Anteil: Vorgabe aus dem LV-Kopf, ohne Vorgabe die vier
     * Anteile des Formblatts 223.
     *
     * @return list<CostKind>
     */
    public function componentKinds(BillOfQuantity $bill): array {
        $components = (array) ($bill->up_components ?? []);
        if ($components === []) {
            return [CostKind::Labour, CostKind::Material, CostKind::Equipment, CostKind::Other];
        }
        usort($components, static fn (array $a, array $b): int => (int) $a['no'] <=> (int) $b['no']);

        return array_map(static fn (array $c): CostKind => CostKind::fromGaebComponent($c['category'] ?? null, $c['label'] ?? null), $components);
    }

    public function isEditable(BillOfQuantity $bill): bool {
        return in_array($bill->status, self::EDITABLE, true);
    }

    /**
     * @param  array<int|string, array{unit_price?: string|float|int|null, components?: array<int|string, string|float|int|null>, not_offered?: bool|string|int|null}>  $rows  Positions-ID → Eingabe
     * @return int Anzahl geänderter Positionen
     */
    public function price(BillOfQuantity $bill, array $rows): int {
        if (! $this->isEditable($bill)) {
            throw new RuntimeException((string) __('gaeb.pricing.locked'));
        }
        $slots = count($this->componentKinds($bill));

        return DB::transaction(function () use ($bill, $rows, $slots): int {
            $changed = 0;
            $items = $bill->items()->whereIn('id', array_map('intval', array_keys($rows)))->get()->keyBy('id');
            foreach ($rows as $id => $row) {
                $item = $items->get((int) $id);
                if (! $item instanceof BoqItem || ! $item->type->isPriceable()) {
                    continue;
                }
                $currency = $item->currency ?? CurrencyCode::Euro;
                $notOffered = filter_var($row['not_offered'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $components = $this->components((array) ($row['components'] ?? []), $slots);
                $unitPrice = match (true) {
                    $notOffered => null,
                    $components !== null => Money::sum(array_map(static fn (string $v): Money => Money::of($v, $currency, 4), $components), $currency, 4),
                    ($row['unit_price'] ?? null) !== null && $row['unit_price'] !== '' => Money::of(NumberHelper::normalizeDecimalString((string) $row['unit_price']), $currency, 4),
                    default => null,
                };
                $item->fill([
                    'not_offered' => $notOffered,
                    'unit_price_components' => $notOffered ? null : $components,
                    'unit_price' => $unitPrice?->getAmount(),
                    'total_price' => $unitPrice === null || $item->quantity === null
                        ? null
                        : DocumentTotalsCalculator::lineNet($item->lineQuantity(), $unitPrice, null, null, $currency)->withScale(2)->getAmount(),
                ]);
                if ($item->isDirty()) {
                    $item->save();
                    $changed++;
                }
            }

            return $changed;
        });
    }

    /**
     * @param  array<int|string, string|float|int|null>  $input
     * @return list<string>|null `null`, wenn kein Anteil eingetragen ist
     */
    private function components(array $input, int $slots): ?array {
        $values = [];
        $any = false;
        for ($i = 0; $i < $slots; $i++) {
            $raw = $input[$i] ?? null;
            if ($raw !== null && $raw !== '') {
                $any = true;
            }
            $values[] = NumberHelper::normalizeDecimalString((string) ($raw === null || $raw === '' ? '0' : $raw));
        }

        return $any ? $values : null;
    }
}
