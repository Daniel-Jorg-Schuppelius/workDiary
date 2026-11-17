<?php
/*
 * Created on   : Tue Nov 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SettleFlatRateShortfallsCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Project;

use App\Console\Concerns\IteratesOrganizations;
use App\Enums\Finance\{TransferChannel, TransferStatus};
use App\Enums\Invoicing\InvoiceStatus;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\Customer\Customer;
use App\Models\Finance\{BillingTransfer, BillingTransferItem};
use App\Models\Integration\ExternalReference;
use App\Models\Invoicing\{Invoice, InvoiceItem};
use App\Models\Platform\Organization;
use App\Models\Project\ProjectTimeShortfall;
use App\Models\Time\TimeEntry;
use App\Modules\ModuleRegistry;
use App\Services\Invoicing\Contracts\TimeVoucherSource;
use App\Services\Invoicing\{ServiceDefaultResolver, TargetTopUps};
use App\Support\MorphMap;
use App\Support\Query\DateRange;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Datenreparatur zu MVP-1708/1716 (MVP-1734): Vorher schloss der Beleg, der
 * die Arbeitszeit eines Pauschal-Projekts abrechnete, dessen Soll-Befunde
 * nicht — sie blieben offen. Der Befehl sucht je offenem oder bestätigtem
 * Befund eines Pauschal-Projekts mit Abrechnung nach Soll den Träger, der
 * Arbeitszeit des Projekts in einem Zeitraum mit dem Befundtag abgerechnet
 * hat — Rechnung aus Zeiten (Leistungszeitraum ihrer Positionen), übertragene
 * Übergabe (ihr Zeitraum) oder Lexoffice-Zeitbeleg (Zeitraum aus dem
 * Belegtext) —, bevorzugt den mit Arbeitszeit der Person an dem Tag, und
 * schließt ihn über dieselbe Regel wie heute (`TargetTopUps::settle…()`).
 * Mehrere gleichrangige Träger meldet er nur. Idempotent: Ein geschlossener
 * Befund trägt einen Verweis und fällt aus der Auswahl.
 *
 * Zeitbelege der Buchhaltungsprogramme kommen über den Erweiterungspunkt
 * {@see TimeVoucherSource}; der Kern kennt kein Plugin.
 */
final class SettleFlatRateShortfallsCommand extends Command {
    use IteratesOrganizations;

    protected $signature = 'projects:settle-flat-rate-shortfalls
        {--dry-run : Nur anzeigen, nichts ändern}
        ' . self::ORGANIZATION_OPTION;

    protected $description = 'Schließt Soll-Befunde von Pauschal-Projekten, deren Arbeitszeit vor MVP-1708 schon abgerechnet wurde, über den abrechnenden Beleg (MVP-1734)';

    public function handle(TargetTopUps $topUps, ServiceDefaultResolver $services): int {
        $dryRun = (bool) $this->option('dry-run');

        $failures = $this->forEachOrganization(function (Organization $organization) use ($topUps, $services, $dryRun): void {
            $candidates = ProjectTimeShortfall::query()
                ->where('organization_id', $organization->id)
                ->unresolved()
                ->whereNull('billed_minutes')
                ->whereNull('invoice_item_id')
                ->whereNull('billing_transfer_position_id')
                ->whereNull('external_reference_id')
                ->where('shortfall_minutes', '>', 0)
                ->whereHas('project', static fn ($project) => $project->where('bills_target_time', true))
                ->with('project.customer')
                ->orderBy('id')
                ->get()
                // Nach Stunden füllt der nächste Lauf auf; nur die Pauschale schließt.
                ->filter(static fn (ProjectTimeShortfall $shortfall): bool => $topUps->coveredByFlatRate($services->resolve($organization, $shortfall->project, TimeEntryKind::Work->value)));
            if ($candidates->isEmpty()) {
                return;
            }

            $settled = 0;
            $unclear = 0;
            $open = 0;
            foreach ($candidates as $shortfall) {
                $label = sprintf('Befund %s (Projekt #%d, Person #%d, %s)', (string) $shortfall->sqid, $shortfall->project_id, $shortfall->user_id, $shortfall->date->toDateString());
                $carrier = $this->carrierOf($shortfall);
                if ($carrier === null) {
                    $open++;
                    $this->line("  {$label}: kein Beleg rechnet Arbeitszeit des Projekts im Zeitraum ab — bleibt offen.");

                    continue;
                }
                if (is_string($carrier)) {
                    $unclear++;
                    $this->warn("  {$label}: nicht eindeutig — {$carrier}; nur gemeldet.");

                    continue;
                }
                if ($dryRun) {
                    $settled++;
                    $this->line("  {$label}: zu schließen über {$carrier['label']}");

                    continue;
                }
                $closed = $this->settle($topUps, $shortfall, $carrier);
                $settled += $closed;
                $this->line("  {$label}: " . ($closed > 0 ? "geschlossen über {$carrier['label']}" : 'inzwischen von anderer Stelle geändert'));
            }

            $verb = $dryRun ? 'zu schließen (Trockenlauf)' : 'geschlossen';
            $this->line("Organisation #{$organization->id}: {$settled} Befunde {$verb}, {$unclear} nicht eindeutig, {$open} ohne abrechnenden Beleg.");
        });

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Der eine Träger nach der Regel von `TargetTopUps::settle()`: Arbeitszeit
     * des Projekts im Zeitraum des Belegs, bevorzugt mit einer Zeit der Person
     * an dem Tag. Null = keiner, Text = mehrere gleichrangige.
     *
     * @return array{model: Invoice|BillingTransfer|ExternalReference, label: string, entries: Collection<int, TimeEntry>, period: array{from: CarbonInterface, to: CarbonInterface}|null}|string|null
     */
    private function carrierOf(ProjectTimeShortfall $shortfall): array|string|null {
        $carriers = [...$this->invoices($shortfall), ...$this->transfers($shortfall), ...$this->vouchers($shortfall)];
        if ($carriers === []) {
            return null;
        }
        $sameDay = array_values(array_filter($carriers, static fn (array $carrier): bool => $carrier['entries']->contains(
            static fn (TimeEntry $entry): bool => (int) $entry->user_id === (int) $shortfall->user_id && $entry->date?->toDateString() === $shortfall->date->toDateString(),
        )));
        $candidates = $sameDay !== [] ? $sameDay : $carriers;
        if (count($candidates) === 1) {
            return $candidates[0];
        }

        return ($sameDay !== [] ? 'mehrere Belege mit Arbeitszeit der Person an dem Tag: ' : 'mehrere Belege rechnen Arbeitszeit des Projekts im Zeitraum ab: ')
            . implode(', ', array_column($candidates, 'label'));
    }

    /**
     * Rechnungen aus Zeiten (nicht storniert), deren Leistungszeitraum den Tag umfasst.
     *
     * @return list<array{model: Invoice, label: string, entries: Collection<int, TimeEntry>, period: null}>
     */
    private function invoices(ProjectTimeShortfall $shortfall): array {
        $work = static fn ($entries) => $entries->where('time_entries.project_id', $shortfall->project_id)->where('time_entries.kind', TimeEntryKind::Work->value);
        $invoices = Invoice::query()
            ->where('organization_id', $shortfall->organization_id)
            ->where('status', '!=', InvoiceStatus::Cancelled->value)
            ->whereNotIn('type', [Invoice::TYPE_CREDIT_NOTE, Invoice::TYPE_CANCELLATION, Invoice::TYPE_PROFORMA])
            ->whereHas('items.timeEntries', $work)
            ->with(['items.timeEntries' => $work])
            ->orderBy('id')
            ->get();

        $found = [];
        foreach ($invoices as $invoice) {
            $from = $invoice->serviceDateFrom();
            $to = $invoice->serviceDateTo();
            if ($from === null || $to === null || $shortfall->date->lt($from->copy()->startOfDay()) || $shortfall->date->gt($to->copy()->endOfDay())) {
                continue;
            }
            $found[] = [
                'model' => $invoice,
                'label' => "Rechnung #{$invoice->id}",
                'entries' => $invoice->items->flatMap(static fn (InvoiceItem $item): Collection => $item->timeEntries->toBase())->values(),
                'period' => null,
            ];
        }

        return $found;
    }

    /**
     * Übertragene Übergaben aus Zeiten, deren Zeitraum den Tag umfasst.
     *
     * @return list<array{model: BillingTransfer, label: string, entries: Collection<int, TimeEntry>, period: null}>
     */
    private function transfers(ProjectTimeShortfall $shortfall): array {
        $day = $shortfall->date->toDateString();
        $workOfProject = TimeEntry::query()->select('id')->where('project_id', $shortfall->project_id)->where('kind', TimeEntryKind::Work->value);
        $transfers = BillingTransfer::query()
            ->where('organization_id', $shortfall->organization_id)
            ->where('customer_id', $shortfall->project->customer_id)
            ->where('status', TransferStatus::Transferred->value)
            ->where('channel', TransferChannel::Time->value)
            ->where(static fn ($query) => $query->whereNull('period_from')->orWhere('period_from', '<=', $day))
            ->where(static fn ($query) => $query->whereNull('period_to')->orWhere('period_to', '>=', $day))
            ->whereHas('items', static fn ($items) => $items->where('source_type', MorphMap::alias(TimeEntry::class))->whereIn('source_id', $workOfProject))
            ->orderBy('id')
            ->get();

        $found = [];
        foreach ($transfers as $transfer) {
            $ids = BillingTransferItem::query()->where('billing_transfer_id', $transfer->id)->where('source_type', MorphMap::alias(TimeEntry::class))->pluck('source_id');
            $found[] = [
                'model' => $transfer,
                'label' => "Übergabe #{$transfer->id}",
                'entries' => TimeEntry::query()->whereIn('id', $ids)->where('project_id', $shortfall->project_id)->where('kind', TimeEntryKind::Work->value)->get(['id', 'project_id', 'user_id', 'date', 'kind'])->toBase(),
                'period' => null,
            ];
        }

        return $found;
    }

    /**
     * Zeitbelege der Buchhaltungsprogramme ({@see TimeVoucherSource}), deren
     * Zeitraum den Tag umfasst — auch eines inzwischen abgeschalteten Programms,
     * es hat die Zeit trotzdem abgerechnet. Welche Zeiten ein Beleg trug, hält er
     * nicht fest: abgerechnete Arbeitszeit des Projekts im Zeitraum, vor dem Beleg
     * angelegt und von keiner Rechnung oder Übergabe abgerechnet.
     *
     * @return list<array{model: ExternalReference, label: string, entries: Collection<int, TimeEntry>, period: array{from: CarbonInterface, to: CarbonInterface}}>
     */
    private function vouchers(ProjectTimeShortfall $shortfall): array {
        $customer = $shortfall->project->customer;
        if (! $customer instanceof Customer) {
            return [];
        }
        $vouchers = [];
        foreach (app(ModuleRegistry::class)->extensions(TimeVoucherSource::class) as $source) {
            $instance = app($source);
            if ($instance instanceof TimeVoucherSource) {
                array_push($vouchers, ...$instance->timeVouchers($customer));
            }
        }

        $found = [];
        foreach ($vouchers as ['reference' => $voucher, 'label' => $voucherLabel, 'from' => $from, 'to' => $to]) {
            $period = ['from' => $from, 'to' => $to];
            if ($shortfall->date->lt($from) || $shortfall->date->gt($to)) {
                continue;
            }
            $entries = TimeEntry::query()
                ->where('organization_id', $shortfall->organization_id)
                ->where('project_id', $shortfall->project_id)
                ->where('kind', TimeEntryKind::Work->value)
                ->where('exported', true)
                ->whereBetween('date', DateRange::days($period['from'], $period['to']))
                ->when($voucher->synced_at !== null, static fn ($query) => $query->where('created_at', '<=', $voucher->synced_at))
                ->whereNotExists(static function ($pivot): void {
                    $pivot->from('invoice_item_time_entries')
                        ->join('invoice_items', 'invoice_items.id', '=', 'invoice_item_time_entries.invoice_item_id')
                        ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
                        ->whereColumn('invoice_item_time_entries.time_entry_id', 'time_entries.id')
                        ->where('invoices.status', '!=', InvoiceStatus::Cancelled->value);
                })
                ->whereNotExists(static function ($items): void {
                    $items->from('billing_transfer_items')
                        ->join('billing_transfers', 'billing_transfers.id', '=', 'billing_transfer_items.billing_transfer_id')
                        ->whereColumn('billing_transfer_items.source_id', 'time_entries.id')
                        ->where('billing_transfer_items.source_type', MorphMap::alias(TimeEntry::class))
                        ->whereIn('billing_transfers.status', [TransferStatus::Confirmed->value, TransferStatus::Transferred->value])
                        ->whereNull('billing_transfers.deleted_at');
                })
                ->get(['id', 'project_id', 'user_id', 'date', 'kind']);
            if ($entries->isEmpty()) {
                continue;
            }
            $found[] = [
                'model' => $voucher,
                'label' => $voucherLabel,
                'entries' => $entries->toBase(),
                'period' => $period,
            ];
        }

        return $found;
    }

    /**
     * Schließt über die Methode des Trägers; sie liest den Befund unter der Sperre neu.
     *
     * @param  array{model: Invoice|BillingTransfer|ExternalReference, label: string, entries: Collection<int, TimeEntry>, period: array{from: CarbonInterface, to: CarbonInterface}|null}  $carrier
     */
    private function settle(TargetTopUps $topUps, ProjectTimeShortfall $shortfall, array $carrier): int {
        $selection = new EloquentCollection([$shortfall]);
        $model = $carrier['model'];

        return match (true) {
            $model instanceof Invoice => $topUps->settleFlatRate($selection, $model),
            $model instanceof BillingTransfer => $topUps->settleFlatRateByTransfer($selection, $model),
            $carrier['period'] !== null && $shortfall->project->customer instanceof Customer => $topUps->settleFlatRateByVoucher($shortfall->project->customer, $carrier['period'], $carrier['entries'], $model, $selection),
            default => 0,
        };
    }
}
