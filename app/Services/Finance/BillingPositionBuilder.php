<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillingPositionBuilder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\Finance\TransferChannel;
use App\Models\Finance\{BillingTransfer, BillingTransferPosition};
use App\Models\{MaterialUsage, TimeEntry};
use App\Services\Invoicing\{BillableTimeAggregator, BlockPrice, BlockPriceResolver, ServiceDefaultResolver, TextCorrectionService};
use CommonToolkit\Helper\Data\StringHelper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Erzeugt die Rechnungssicht einer Übergabe (MVP-487): Taktung und
 * Zusammenfassung über den {@see BillableTimeAggregator}, Preis über den
 * {@see BlockPriceResolver}, Bezeichnung/Einheit/Standardtext über den
 * {@see ServiceDefaultResolver}.
 *
 * `build()` liefert die Positionen unpersistiert (Vorschau im Entwurf),
 * `freeze()` schreibt exakt dieselben fest — dadurch zeigt die Vorschau, was
 * später gesendet wird. Nach dem Einfrieren sind die Positionen die Quelle
 * aller Ziele; die Quell-Zuordnung bleibt in `billing_transfer_items`.
 */
class BillingPositionBuilder {
    public function __construct(
        private readonly BillableTimeAggregator $aggregator,
        private readonly BlockPriceResolver $prices,
        private readonly ServiceDefaultResolver $services,
        private readonly TextCorrectionService $corrections,
    ) {}

    /**
     * Positionen des Transfers: eingefrorene, falls vorhanden, sonst frisch
     * berechnet.
     *
     * @return Collection<int, BillingTransferPosition>
     */
    public function positionsFor(BillingTransfer $transfer): Collection {
        $frozen = $transfer->positions()->get();

        return $frozen->isNotEmpty() ? $frozen : $this->build($transfer);
    }

    /**
     * Berechnet die Positionen, ohne sie zu speichern.
     *
     * @return Collection<int, BillingTransferPosition>
     */
    public function build(BillingTransfer $transfer): Collection {
        return $transfer->channel === TransferChannel::Time
            ? $this->buildTimePositions($transfer)
            : $this->buildMaterialPositions($transfer);
    }

    /**
     * Schreibt die Positionen fest (idempotent: ersetzt eine frühere Fassung).
     *
     * @return Collection<int, BillingTransferPosition>
     */
    public function freeze(BillingTransfer $transfer): Collection {
        $positions = $this->build($transfer);

        return DB::transaction(function () use ($transfer, $positions): Collection {
            $transfer->positions()->delete();
            foreach ($positions as $position) {
                $position->save();
            }

            return $transfer->positions()->get();
        });
    }

    /** @return Collection<int, BillingTransferPosition> */
    private function buildTimePositions(BillingTransfer $transfer): Collection {
        $ids = $transfer->items->where('source_type', TimeEntry::class)->pluck('source_id')->all();

        /** @var \Illuminate\Database\Eloquent\Collection<int, TimeEntry> $entries */
        $entries = TimeEntry::query()
            ->whereIn('id', $ids)
            ->with(['user:id,name', 'project.parent', 'project.customer', 'project.foreignCustomer'])
            ->orderBy('date')
            ->get();
        $entriesById = $entries->keyBy('id');

        $positions = new Collection;
        $index = 0;

        foreach ($this->aggregator->aggregate($entries) as $block) {
            $hours = $block->billedHours();
            if ($hours <= 0) {
                continue;
            }

            /** @var TimeEntry|null $primary */
            $primary = $entriesById->get($block->primaryEntryId);
            $service = $this->services->resolve(
                $transfer->organization,
                $block->project,
                $block->kind?->value,
            );
            $price = $this->prices->resolve(
                $block,
                $primary,
                $transfer->customer,
                $service,
                (int) $transfer->organization_id,
            );

            /** @var Collection<int, TimeEntry> $blockEntries */
            $blockEntries = collect($block->entryIds)
                ->map(fn(int $id): ?TimeEntry => $entriesById->get($id))
                ->filter();

            $from = $block->firstStart?->toDateString() ?? $primary?->date?->toDateString();
            $to = $block->lastEnd?->toDateString() ?? $from;

            $positions->push(new BillingTransferPosition([
                'organization_id' => $transfer->organization_id,
                'billing_transfer_id' => $transfer->id,
                'position' => ++$index,
                'source_kind' => BillingTransferPosition::KIND_TIME,
                'project_id' => $block->project?->id,
                'kind' => $block->kind?->value,
                'source_ids' => $block->entryIds,
                'primary_source_id' => $block->primaryEntryId,
                'name' => $this->positionName($block, $transfer, $service?->name),
                'description' => $this->positionText($service?->standardText, $blockEntries, $from, $to, (int) $transfer->organization_id),
                'quantity' => round($hours, 3),
                'unit_name' => $service?->unitName ?: $this->timeUnit($transfer),
                'unit_price' => round($price->rate, 4),
                'vat_rate' => $service?->vatRate,
                'amount' => round($hours * $price->rate, 2),
                'article_id' => $service?->articleId,
                'service_source' => $service?->source,
                'price_source' => $price->source,
                'service_from' => $from,
                'service_to' => $to,
            ]));
        }

        return $positions;
    }

    /** @return Collection<int, BillingTransferPosition> */
    private function buildMaterialPositions(BillingTransfer $transfer): Collection {
        $items = $transfer->items->where('source_type', MaterialUsage::class)->keyBy('source_id');

        $usages = MaterialUsage::query()
            ->whereIn('id', $items->keys()->all())
            ->with(['timesheet:id,work_date,project_id', 'timesheet.project:id,name'])
            ->get()
            ->sortBy(fn(MaterialUsage $u): string => $u->timesheet?->work_date?->toDateString() ?? '')
            ->values();

        $positions = new Collection;
        $index = 0;

        foreach ($usages as $usage) {
            $item = $items->get($usage->id);
            $itemQuantity = $item !== null ? $item->quantity : null;
            $quantity = round((float) ($itemQuantity ?? ($usage->quantity?->getValue()->toFloat() ?? 0.0)), 3);
            $unitPrice = $usage->unit_price?->toFloat() ?? 0.0;
            $date = $usage->timesheet?->work_date?->toDateString();

            $positions->push(new BillingTransferPosition([
                'organization_id' => $transfer->organization_id,
                'billing_transfer_id' => $transfer->id,
                'position' => ++$index,
                'source_kind' => BillingTransferPosition::KIND_MATERIAL,
                'project_id' => $usage->timesheet?->project_id,
                'kind' => null,
                'source_ids' => [(int) $usage->id],
                'primary_source_id' => (int) $usage->id,
                'name' => (string) $this->corrections->apply(trim((string) $usage->description), (int) $transfer->organization_id) ?: (string) __('Material'),
                'description' => $date !== null
                    ? (string) __('finance.position.service_date', ['date' => \Illuminate\Support\Carbon::parse($date)->format('d.m.Y')])
                    : null,
                'quantity' => $quantity,
                'unit_name' => trim((string) ($usage->unit ?? '')) ?: (string) __('invoicing.unit_piece'),
                'unit_price' => round($unitPrice, 4),
                'vat_rate' => $usage->tax_rate !== null ? round((float) $usage->tax_rate->getNumericValue(), 2) : null,
                'amount' => round((float) (($item !== null ? $item->amount : null) ?? ($quantity * $unitPrice)), 2),
                'article_id' => null,
                'service_source' => null,
                'price_source' => $unitPrice > 0.0 ? BlockPrice::SOURCE_ENTRY : BlockPrice::SOURCE_NONE,
                'service_from' => $date,
                'service_to' => $date,
            ]));
        }

        return $positions;
    }

    /** Zeit-Einheit der Organisation (`invoicing.time_unit`), Default „h". */
    private function timeUnit(BillingTransfer $transfer): string {
        $unit = trim((string) ($transfer->organization?->invoicingSettings()['time_unit'] ?? ''));

        return $unit !== '' ? $unit : (string) __('invoicing.unit_hour');
    }

    /**
     * Bezeichnung: Name der Standardleistung, sonst wie bisher
     * Projekt + Tätigkeitsart + Zeitraum.
     */
    private function positionName(\App\Services\Invoicing\BillingBlock $block, BillingTransfer $transfer, ?string $serviceName): string {
        $serviceName = trim((string) $serviceName);

        return $serviceName !== '' ? $serviceName : $block->displayName($transfer);
    }

    /**
     * Positionstext: Standardtext der Leistung, darunter der Leistungstext aus
     * den Zeiteinträgen (dedupliziert) und das Leistungsdatum. Der Leistungstext
     * ist die Grundlage für den KI-Vorschlag (MVP-488).
     *
     * @param  Collection<int, TimeEntry>  $entries
     */
    private function positionText(?string $standardText, Collection $entries, ?string $from, ?string $to, int $organizationId): ?string {
        $parts = [];

        $standardText = trim((string) $standardText);
        if ($standardText !== '') {
            $parts[] = $standardText;
        }

        // Wörterbuch vor unique(): korrigierte Duplikate fallen zusammen.
        // Standardtext (kuratierte Stammdaten) und Datums-Label bleiben unkorrigiert.
        $work = $entries
            ->map(fn(TimeEntry $e): string => (string) $this->corrections->apply(StringHelper::normalizeWhitespace((string) $e->description), $organizationId))
            ->filter(fn(string $text): bool => $text !== '')
            ->unique()
            ->implode('; ');
        if ($work !== '') {
            $parts[] = $work;
        }

        $date = self::serviceDateLabel($from, $to);
        if ($date !== null) {
            $parts[] = $date;
        }

        return $parts === [] ? null : implode("\n", $parts);
    }

    /** „(Leistungsdatum 12.05.2026)" bzw. „(Leistungszeitraum 12.05.–14.05.2026)". */
    public static function serviceDateLabel(?string $from, ?string $to): ?string {
        if ($from === null) {
            return null;
        }

        $start = \Illuminate\Support\Carbon::parse($from);
        $end = $to !== null ? \Illuminate\Support\Carbon::parse($to) : $start;

        return $start->toDateString() === $end->toDateString()
            ? (string) __('finance.position.service_date', ['date' => $start->format('d.m.Y')])
            : (string) __('finance.position.service_period', ['from' => $start->format('d.m.Y'), 'to' => $end->format('d.m.Y')]);
    }
}
