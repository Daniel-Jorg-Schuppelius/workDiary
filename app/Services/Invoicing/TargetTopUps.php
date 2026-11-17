<?php
/*
 * Created on   : Sun Nov 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TargetTopUps.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing;

use App\Enums\Finance\TransferStatus;
use App\Enums\Project\ShortfallStatus;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\Customer\{Customer, ForeignCustomer};
use App\Models\Finance\{BillingTransfer, BillingTransferPosition};
use App\Models\Integration\ExternalReference;
use App\Models\Invoicing\{Invoice, InvoiceItem};
use App\Models\Platform\Organization;
use App\Models\Project\{Project, ProjectTimeShortfall};
use App\Models\Time\TimeEntry;
use App\Services\Concerns\AssertsStatusTransition;
use App\Services\Invoicing\Dto\TargetTopUpLine;
use App\Support\{CarbonFmt, MorphMap};
use App\Support\Query\DateRange;
use Carbon\CarbonInterface;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Database\Eloquent\{Builder, Collection as EloquentCollection};
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\{Carbon, Collection};
use Illuminate\Support\Facades\DB;

/**
 * Auffüllung auf das Projekt-Soll (Feature 194, MVP-1470/1494) für Übergabe
 * und lokale Rechnung: dieselben offenen Befunde, derselbe Preis. Berechnet
 * ist ein Befund, sobald `billed_minutes` gesetzt ist — über eine
 * Übergabeposition oder eine Rechnungsposition (`invoice_item_id`).
 * Rechnet das Projekt pauschal ab, schließt der Beleg mit der Pauschale den
 * Befund statt aufzufüllen — Rechnungslauf (MVP-1708), Übergabe und
 * Lexoffice-Zeitbeleg (MVP-1716).
 */
final class TargetTopUps {
    use AssertsStatusTransition;

    public function __construct(
        private readonly BlockPriceResolver $prices,
        private readonly ServiceDefaultResolver $services,
    ) {}

    /**
     * Offene oder bestätigte Befunde der Kundenprojekte mit Abrechnung nach
     * Soll im Zeitraum, noch nicht berechnet und von keiner bestätigten oder
     * übergebenen Übergabe gehalten ({@see whereTransferHolds()}).
     *
     * @param  array{from?: string|CarbonInterface|null, to?: string|CarbonInterface|null}  $period
     * @return Builder<ProjectTimeShortfall>
     */
    public function openQuery(Customer $customer, array $period, ?Project $project = null, ?ForeignCustomer $foreignCustomer = null): Builder {
        $query = ProjectTimeShortfall::query()
            ->unresolved()
            ->whereNull('billed_minutes')
            ->where('shortfall_minutes', '>', 0)
            ->whereHas('project', fn($q) => $q->where('customer_id', $customer->id)->where('bills_target_time', true)
                ->when($foreignCustomer !== null, fn($q) => $q->where('foreign_customer_id', $foreignCustomer?->id)));
        if ($project !== null) {
            $query->where('project_id', $project->id);
        }
        if (! empty($period['from'])) {
            $query->where('date', '>=', Carbon::parse($period['from'])->toDateString());
        }
        if (! empty($period['to'])) {
            $query->where('date', '<', DateRange::dayAfter(Carbon::parse($period['to'])));
        }

        return $query->whereNotExists(function (QueryBuilder $sub): void {
            $sub->from('billing_transfer_items')
                ->join('billing_transfers', 'billing_transfers.id', '=', 'billing_transfer_items.billing_transfer_id')
                ->whereColumn('billing_transfer_items.source_id', 'project_time_shortfalls.id')
                ->where('billing_transfer_items.source_type', MorphMap::alias(ProjectTimeShortfall::class))
                ->whereNull('billing_transfers.deleted_at');
            self::whereTransferHolds($sub);
        });
    }

    /**
     * Hält eine bestätigte oder übergebene Übergabe den Befund (MVP-1722)? Nur,
     * wenn sie ihn auffüllt (Auffüllposition) oder mit der Pauschale schließen
     * kann (Arbeitszeit seines Projekts, die Regel von {@see settle()}). Einen
     * Pauschal-Befund ohne Arbeitszeit seines Projekts lässt sie offen — er
     * bleibt für die nächste Übergabe oder Rechnung frei, statt bis zum Storno
     * reserviert zu sein. Die Abfrage führt `billing_transfers` und
     * `project_time_shortfalls`.
     */
    public static function whereTransferHolds(QueryBuilder $query): void {
        $query->whereIn('billing_transfers.status', [TransferStatus::Confirmed->value, TransferStatus::Transferred->value])
            ->where(static function (QueryBuilder $holds): void {
                $holds->whereExists(static function (QueryBuilder $topUp): void {
                    $topUp->from('billing_transfer_positions')
                        ->whereColumn('billing_transfer_positions.billing_transfer_id', 'billing_transfers.id')
                        ->where('billing_transfer_positions.source_kind', BillingTransferPosition::KIND_TARGET)
                        ->whereColumn('billing_transfer_positions.primary_source_id', 'project_time_shortfalls.id');
                })->orWhereExists(static function (QueryBuilder $work): void {
                    $work->from('billing_transfer_items as held_time')
                        ->join('time_entries', 'time_entries.id', '=', 'held_time.source_id')
                        ->whereColumn('held_time.billing_transfer_id', 'billing_transfers.id')
                        ->where('held_time.source_type', MorphMap::alias(TimeEntry::class))
                        ->whereColumn('time_entries.project_id', 'project_time_shortfalls.project_id')
                        ->where('time_entries.kind', TimeEntryKind::Work->value);
                });
            });
    }

    /**
     * Je Befund eine Zeile mit der fehlenden Zeit; Preis über den
     * BlockPriceResolver wie bei einem Zeitblock ohne Einträge (Projektregel,
     * Kundensatz, Standardleistung, Org-Standard). Rechnet das Projekt die
     * Arbeit pauschal ab, entfällt die Zeile ({@see settleFlatRate()}).
     *
     * @param  EloquentCollection<int, ProjectTimeShortfall>  $shortfalls
     * @return Collection<int, TargetTopUpLine>
     */
    public function lines(EloquentCollection $shortfalls, ?Customer $customer, ?Organization $organization): Collection {
        $shortfalls->loadMissing(['user:id,name', 'project.parent', 'project.customer', 'project.foreignCustomer']);
        $lines = new Collection;
        foreach ($shortfalls as $shortfall) {
            $minutes = (int) $shortfall->shortfall_minutes;
            if ($minutes <= 0) {
                continue;
            }
            $project = $shortfall->project;
            $service = $this->services->resolve($organization, $project, TimeEntryKind::Work->value);
            if ($this->coveredByFlatRate($service)) {
                continue;
            }
            $block = new BillingBlock($project, TimeEntryKind::Work, [], 0, $minutes, $minutes, $minutes, 0.0, null, null, null);
            $price = $this->prices->resolve($block, null, $customer, $service, $organization !== null ? (int) $organization->id : (int) $shortfall->organization_id);
            $lines->push(new TargetTopUpLine(
                $shortfall,
                $minutes,
                $block->billedHours(),
                $price,
                $service,
                (string) __('Vereinbarte Einsatzzeit') . ' — ' . $shortfall->user->name . ', ' . CarbonFmt::fdate($shortfall->date),
                (string) __('Soll :target h, gebucht :booked h — Auffüllung :missing h', [
                    'target' => NumberHelper::toGermanFormat($shortfall->target_minutes / 60, 2),
                    'booked' => NumberHelper::toGermanFormat($shortfall->booked_minutes / 60, 2),
                    'missing' => NumberHelper::toGermanFormat($minutes / 60, 2),
                ]),
            ));
        }

        return $lines;
    }

    /**
     * Befunde, deren Projekt die Arbeit pauschal abrechnet, schließt die
     * Rechnung mit dieser Pauschale (MVP-1708): Erledigt mit Verweis auf die
     * Position, die die Arbeitszeit des Projekts abrechnet ({@see settle()}).
     *
     * @param  EloquentCollection<int, ProjectTimeShortfall>  $shortfalls
     * @return int geschlossene Befunde
     */
    public function settleFlatRate(EloquentCollection $shortfalls, Invoice $invoice): int {
        $items = $invoice->items()->whereHas('timeEntries')->with('timeEntries:time_entries.id,time_entries.project_id,time_entries.user_id,time_entries.date,time_entries.kind')->get();

        return $this->settle($shortfalls, $invoice->organization, $items->mapWithKeys(static fn (InvoiceItem $item): array => [(int) $item->id => $item->timeEntries->toBase()]), 'invoice_item_id');
    }

    /**
     * Dieselbe Schließung beim Übertragen einer Übergabe (MVP-1716): Verweis
     * auf die Zeitposition, die die Pauschale abrechnet.
     *
     * @param  EloquentCollection<int, ProjectTimeShortfall>  $shortfalls
     * @return int geschlossene Befunde
     */
    public function settleFlatRateByTransfer(EloquentCollection $shortfalls, BillingTransfer $transfer): int {
        $positions = $transfer->positions()->where('source_kind', BillingTransferPosition::KIND_TIME)->get();
        $entries = TimeEntry::query()
            ->whereIn('id', $positions->flatMap(static fn (BillingTransferPosition $position): array => $position->source_ids ?? [])->unique()->values()->all())
            ->get(['id', 'project_id', 'user_id', 'date', 'kind'])
            ->keyBy('id');

        return $this->settle($shortfalls, $transfer->organization, $positions->mapWithKeys(static fn (BillingTransferPosition $position): array => [
            (int) $position->id => collect($position->source_ids ?? [])->map(static fn (int|string $id): ?TimeEntry => $entries->get((int) $id))->filter()->values(),
        ]), 'billing_transfer_position_id');
    }

    /**
     * Dieselbe Schließung durch einen Zeitbeleg im Buchhaltungsprogramm
     * (Lexoffice, MVP-1716): ein Beleg für alle Zeiten des Zeitraums, Verweis
     * auf seine Fremdreferenz. Befunde aus der Auswahl der Rechnung.
     *
     * @param  array{from?: string|CarbonInterface|null, to?: string|CarbonInterface|null}  $period
     * @param  Collection<int, TimeEntry>  $entries  abgerechnete Zeiten des Belegs
     * @return int geschlossene Befunde
     */
    public function settleFlatRateByVoucher(Customer $customer, array $period, Collection $entries, ExternalReference $voucher): int {
        return $this->settle($this->openQuery($customer, $period)->orderBy('date')->get(), $customer->organization, new Collection([(int) $voucher->id => $entries]), 'external_reference_id');
    }

    /**
     * Ein mit der Pauschale geschlossener Befund ist wieder offen (Position
     * entfernt, Entwurf verworfen, Storno von Rechnung oder Übergabe).
     *
     * @param  iterable<ProjectTimeShortfall>  $shortfalls
     * @return int wieder geöffnete Befunde
     */
    public function reopenFlatRate(iterable $shortfalls): int {
        $reopened = 0;
        foreach ($shortfalls as $shortfall) {
            $reopened += (int) DB::transaction(function () use ($shortfall): bool {
                $locked = ProjectTimeShortfall::query()->whereKey($shortfall->id)->lockForUpdate()->first();
                if (! $locked instanceof ProjectTimeShortfall || ! $locked->isSettledByFlatRate()) {
                    return false;
                }
                $this->assertStatusTransition($locked->status, ShortfallStatus::Open);
                $locked->update(['status' => ShortfallStatus::Open, 'resolved_at' => null, 'invoice_item_id' => null, 'billing_transfer_position_id' => null, 'external_reference_id' => null]);
                $shortfall->setRawAttributes($locked->getAttributes(), true);

                return true;
            });
        }

        return $reopened;
    }

    /**
     * Die Regel der Pauschal-Schließung für Rechnung, Übergabe und Zeitbeleg:
     * Erledigt mit Verweis auf den Träger, der Arbeitszeit des Projekts
     * abrechnet — bevorzugt den mit einer Zeit der Person an dem Tag. Ohne
     * abgerechnete Arbeitszeit des Projekts bleibt der Befund offen.
     * `billed_minutes` bleibt leer: Aufgefüllt wurde nichts, eine Nachbuchung
     * bleibt abrechenbar.
     *
     * @param  EloquentCollection<int, ProjectTimeShortfall>  $shortfalls
     * @param  Collection<int, Collection<int, TimeEntry>>  $carriers  Träger-ID => abgerechnete Zeiten
     * @param  'invoice_item_id'|'billing_transfer_position_id'|'external_reference_id'  $reference
     */
    private function settle(EloquentCollection $shortfalls, ?Organization $organization, Collection $carriers, string $reference): int {
        $settled = 0;
        foreach ($shortfalls as $shortfall) {
            if ($shortfall->isBilled() || $shortfall->status === ShortfallStatus::Resolved
                || ! $this->coveredByFlatRate($this->services->resolve($organization, $shortfall->project, TimeEntryKind::Work->value))) {
                continue;
            }
            $ofProject = $carriers->filter(static fn (Collection $entries): bool => $entries->contains(
                static fn (TimeEntry $entry): bool => (int) $entry->project_id === (int) $shortfall->project_id && $entry->kind === TimeEntryKind::Work,
            ));
            $carrier = $ofProject->search(static fn (Collection $entries): bool => $entries->contains(
                static fn (TimeEntry $entry): bool => (int) $entry->user_id === (int) $shortfall->user_id && $entry->date?->toDateString() === $shortfall->date->toDateString(),
            ));
            $carrier = $carrier !== false ? $carrier : $ofProject->keys()->first();
            if ($carrier === null) {
                continue;
            }
            // Unter der Sperre neu lesen: ein paralleler Lauf kann den Befund schon geschlossen oder berechnet haben.
            $settled += (int) DB::transaction(function () use ($shortfall, $carrier, $reference): bool {
                $locked = ProjectTimeShortfall::query()->whereKey($shortfall->id)->lockForUpdate()->first();
                if (! $locked instanceof ProjectTimeShortfall || $locked->isBilled() || $locked->status === ShortfallStatus::Resolved) {
                    return false;
                }
                $this->assertStatusTransition($locked->status, ShortfallStatus::Resolved);
                $locked->update(['status' => ShortfallStatus::Resolved, 'resolved_at' => now(), $reference => (int) $carrier]);
                $shortfall->setRawAttributes($locked->getAttributes(), true);

                return true;
            });
        }

        return $settled;
    }

    public function markInvoiced(ProjectTimeShortfall $shortfall, InvoiceItem $item): void {
        $shortfall->forceFill([
            'billed_minutes' => (int) $shortfall->shortfall_minutes,
            'billed_at' => now(),
            'invoice_item_id' => $item->id,
        ])->saveQuietly();
    }

    /**
     * Position entfernt, Entwurf verworfen oder Vollstorno: die Auffüllung
     * wird wieder abrechenbar, wie beim Storno einer Übergabe; ein mit der
     * Pauschale geschlossener Befund ist wieder offen.
     *
     * @param  array<int, int>  $invoiceItemIds
     */
    public function releaseInvoiceItems(array $invoiceItemIds): int {
        if ($invoiceItemIds === []) {
            return 0;
        }
        $reopened = $this->reopenFlatRate(ProjectTimeShortfall::query()->whereIn('invoice_item_id', $invoiceItemIds)->get());

        return $reopened + ProjectTimeShortfall::query()->whereIn('invoice_item_id', $invoiceItemIds)
            ->update(['billed_minutes' => null, 'billed_at' => null, 'invoice_item_id' => null]);
    }

    /** Eine Pauschale deckt den Auftrag unabhängig von der Dauer (MVP-1678): kein Auffüllen auf Soll-Stunden. */
    public function coveredByFlatRate(?ResolvedService $service): bool {
        return $service !== null && ! $service->isHourly();
    }
}
