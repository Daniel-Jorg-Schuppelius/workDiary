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
use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\Customer\{Customer, ForeignCustomer};
use App\Models\Invoicing\InvoiceItem;
use App\Models\Platform\Organization;
use App\Models\Project\{Project, ProjectTimeShortfall};
use App\Services\Invoicing\Dto\TargetTopUpLine;
use App\Support\{CarbonFmt, MorphMap};
use App\Support\Query\DateRange;
use Carbon\CarbonInterface;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Database\Eloquent\{Builder, Collection as EloquentCollection};
use Illuminate\Support\{Carbon, Collection};

/**
 * Auffüllung auf das Projekt-Soll (Feature 194, MVP-1470/1494) für Übergabe
 * und lokale Rechnung: dieselben offenen Befunde, derselbe Preis. Berechnet
 * ist ein Befund, sobald `billed_minutes` gesetzt ist — über eine
 * Übergabeposition oder eine Rechnungsposition (`invoice_item_id`).
 */
final class TargetTopUps {
    public function __construct(
        private readonly BlockPriceResolver $prices,
        private readonly ServiceDefaultResolver $services,
    ) {}

    /**
     * Offene oder bestätigte Befunde der Kundenprojekte mit Abrechnung nach
     * Soll im Zeitraum, noch nicht berechnet und in keiner bestätigten oder
     * übergebenen Übergabe reserviert.
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

        return $query->whereNotExists(function ($sub): void {
            $sub->from('billing_transfer_items')
                ->join('billing_transfers', 'billing_transfers.id', '=', 'billing_transfer_items.billing_transfer_id')
                ->whereColumn('billing_transfer_items.source_id', 'project_time_shortfalls.id')
                ->where('billing_transfer_items.source_type', MorphMap::alias(ProjectTimeShortfall::class))
                ->whereIn('billing_transfers.status', [TransferStatus::Confirmed->value, TransferStatus::Transferred->value])
                ->whereNull('billing_transfers.deleted_at');
        });
    }

    /**
     * Je Befund eine Zeile mit der fehlenden Zeit; Preis über den
     * BlockPriceResolver wie bei einem Zeitblock ohne Einträge (Projektregel,
     * Kundensatz, Standardleistung, Org-Standard). Rechnet das Projekt die
     * Arbeit pauschal ab, entfällt die Zeile; der Befund bleibt offen.
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
            // Eine Pauschale deckt den Auftrag unabhängig von der Dauer (MVP-1678): kein Auffüllen auf Soll-Stunden.
            if ($service !== null && ! $service->isHourly()) {
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

    public function markInvoiced(ProjectTimeShortfall $shortfall, InvoiceItem $item): void {
        $shortfall->forceFill([
            'billed_minutes' => (int) $shortfall->shortfall_minutes,
            'billed_at' => now(),
            'invoice_item_id' => $item->id,
        ])->saveQuietly();
    }

    /**
     * Position entfernt, Entwurf verworfen oder Vollstorno: die Auffüllung
     * wird wieder abrechenbar, wie beim Storno einer Übergabe.
     *
     * @param  array<int, int>  $invoiceItemIds
     */
    public function releaseInvoiceItems(array $invoiceItemIds): int {
        if ($invoiceItemIds === []) {
            return 0;
        }

        return ProjectTimeShortfall::query()->whereIn('invoice_item_id', $invoiceItemIds)
            ->update(['billed_minutes' => null, 'billed_at' => null, 'invoice_item_id' => null]);
    }
}
