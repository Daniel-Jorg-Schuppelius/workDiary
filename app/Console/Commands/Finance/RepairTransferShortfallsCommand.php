<?php
/*
 * Created on   : Tue Nov 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RepairTransferShortfallsCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Finance;

use App\Console\Concerns\IteratesModuleOrganizations;
use App\Enums\Finance\TransferStatus;
use App\Enums\Project\ShortfallStatus;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\Finance\{BillingTransfer, BillingTransferItem, BillingTransferPosition};
use App\Models\Invoicing\InvoiceItem;
use App\Models\Platform\Organization;
use App\Models\Project\ProjectTimeShortfall;
use App\Models\Time\TimeEntry;
use App\Services\Invoicing\{ServiceDefaultResolver, TargetTopUps};
use App\Support\{Locales, MorphMap};
use App\Support\Query\DateRange;
use Carbon\CarbonInterface;
use CommonToolkit\Helper\Data\StringHelper;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

/**
 * Datenreparatur zu MVP-1716 (MVP-1721): Vor MVP-1716 setzte das Übertragen
 * einer Übergabe `billed_minutes` auch an Soll-Befunden ohne Auffüllposition —
 * Pauschal-Projekte, deren Auffüllung nichts berechnete. Nachbuchungen auf
 * diese Tage wurden als „[Soll berechnet]“ nicht abrechenbar. Der Befehl setzt
 * solche Befunde zurück und schließt sie wie MVP-1716 heute, wenn die Übergabe
 * Arbeitszeit ihres Projekts enthält; die markierten Nachbuchungen werden
 * wieder abrechenbar, sofern keine Rechnung oder Übergabe sie abgerechnet hat.
 * Nicht eindeutige Fälle meldet er nur. Idempotent: ein zurückgesetzter Befund
 * erfüllt die Erkennung nicht mehr.
 */
final class RepairTransferShortfallsCommand extends Command {
    use IteratesModuleOrganizations;

    protected $signature = 'finance:repair-transfer-shortfalls
        {--dry-run : Nur anzeigen, nichts ändern}
        ' . self::ORGANIZATION_OPTION;

    protected $description = 'Setzt Pauschal-Befunde zurück, die eine Übergabe vor MVP-1716 ohne Auffüllposition als berechnet markiert hat, und macht ihre Nachbuchungen wieder abrechenbar (MVP-1721)';

    public function handle(TargetTopUps $topUps, ServiceDefaultResolver $services): int {
        $dryRun = (bool) $this->option('dry-run');
        $markers = array_values(array_unique(array_filter(array_map(
            static fn (string $locale): string => trim((string) trans('[Soll berechnet]', [], $locale)),
            Locales::codes(),
        ))));

        $failures = $this->forEachOrganization(function (Organization $organization) use ($topUps, $services, $dryRun, $markers): void {
            $candidates = ProjectTimeShortfall::query()
                ->where('organization_id', $organization->id)
                ->whereNotNull('billed_minutes')
                ->whereNull('invoice_item_id')
                ->whereNull('billing_transfer_position_id')
                ->whereNull('external_reference_id')
                ->whereExists(static function ($items): void {
                    $items->from('billing_transfer_items')
                        ->whereColumn('billing_transfer_items.source_id', 'project_time_shortfalls.id')
                        ->where('billing_transfer_items.source_type', MorphMap::alias(ProjectTimeShortfall::class));
                })
                ->with('project')
                ->orderBy('id')
                ->get();
            if ($candidates->isEmpty()) {
                return;
            }

            $repaired = 0;
            $unclear = 0;
            foreach ($candidates as $shortfall) {
                $label = sprintf('Befund %s (Projekt #%d, Person #%d, %s)', (string) $shortfall->sqid, $shortfall->project_id, $shortfall->user_id, $shortfall->date->toDateString());
                $transfer = $this->transferOf($shortfall, $organization, $topUps, $services);
                if (is_string($transfer)) {
                    $unclear++;
                    $this->warn("  {$label}: nicht eindeutig — {$transfer}; nur gemeldet.");

                    continue;
                }
                if ($dryRun) {
                    $repaired++;
                    $this->line("  {$label} aus Übergabe #{$transfer->id}: zurückzusetzen; Nachbuchungen wieder abrechenbar: "
                        . $this->sqids($this->lateEntries($shortfall, $shortfall->billed_at, $markers, false)));

                    continue;
                }
                $result = DB::transaction(fn (): ?array => $this->repair($shortfall, $transfer, $topUps, $markers));
                if ($result === null) {
                    continue; // inzwischen von anderer Stelle geändert
                }
                $repaired++;
                $this->line(sprintf(
                    '  %s aus Übergabe #%d: zurückgesetzt, %s; Nachbuchungen wieder abrechenbar: %s',
                    $label,
                    $transfer->id,
                    match (true) {
                        $result['settled'] => 'mit der Pauschale der Übergabe erledigt',
                        $result['resolved'] => 'war schon erledigt',
                        default => 'offen (keine Arbeitszeit des Projekts in der Übergabe)',
                    },
                    $this->sqids($result['entries']),
                ));
            }

            $verb = $dryRun ? 'zurückzusetzen (Trockenlauf)' : 'zurückgesetzt';
            $this->line("Organisation #{$organization->id}: {$repaired} Befunde {$verb}, {$unclear} nicht eindeutig.");
        });

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Die eine übertragene Übergabe, die den Befund ohne Auffüllposition als
     * berechnet markiert hat; sonst der Grund, warum der Fall nicht eindeutig ist.
     */
    private function transferOf(ProjectTimeShortfall $shortfall, Organization $organization, TargetTopUps $topUps, ServiceDefaultResolver $services): BillingTransfer|string {
        $holders = BillingTransfer::query()
            ->whereIn('status', [TransferStatus::Confirmed->value, TransferStatus::Transferred->value])
            ->whereHas('items', static fn ($items) => $items->where('source_type', MorphMap::alias(ProjectTimeShortfall::class))->where('source_id', $shortfall->id))
            ->get();
        if ($holders->count() !== 1) {
            return $holders->isEmpty()
                ? 'keine bestätigte oder übertragene Übergabe enthält ihn'
                : 'mehrere Übergaben enthalten ihn (#' . $holders->pluck('id')->implode(', #') . ')';
        }
        $transfer = $holders->sole();
        if ($transfer->status !== TransferStatus::Transferred) {
            return "Übergabe #{$transfer->id} ist noch nicht übertragen";
        }
        if ($transfer->positions()->where('source_kind', BillingTransferPosition::KIND_TARGET)->where('primary_source_id', $shortfall->id)->exists()) {
            return "Übergabe #{$transfer->id} hat eine Auffüllposition für ihn";
        }
        // Ohne Auffüllposition rechnete das Projekt pauschal ab; rechnet es heute nach Stunden, ist offen, was gemeint war.
        if ($shortfall->shortfall_minutes > 0 && ! $topUps->coveredByFlatRate($services->resolve($organization, $shortfall->project, TimeEntryKind::Work->value))) {
            return 'das Projekt rechnet heute nicht pauschal ab';
        }

        return $transfer;
    }

    /**
     * Unter der Sperre neu lesen und zurücksetzen; die Schließung über die
     * Pauschale prüft den Status selbst ({@see TargetTopUps::settleFlatRateByTransfer()}).
     *
     * @param  list<string>  $markers
     * @return array{settled: bool, resolved: bool, entries: EloquentCollection<int, TimeEntry>}|null
     */
    private function repair(ProjectTimeShortfall $shortfall, BillingTransfer $transfer, TargetTopUps $topUps, array $markers): ?array {
        $locked = ProjectTimeShortfall::query()->whereKey($shortfall->id)->with('project')->lockForUpdate()->first();
        if (! $locked instanceof ProjectTimeShortfall || $locked->billed_minutes === null || $locked->invoice_item_id !== null
            || $locked->billing_transfer_position_id !== null || $locked->external_reference_id !== null) {
            return null;
        }
        $billedAt = $locked->billed_at;
        $locked->update(['billed_minutes' => null, 'billed_at' => null]);
        $settled = $topUps->settleFlatRateByTransfer(new EloquentCollection([$locked]), $transfer) > 0;

        $entries = $this->lateEntries($locked, $billedAt, $markers, true);
        foreach ($entries as $entry) {
            $description = (string) $entry->description;
            foreach ($markers as $marker) {
                $description = str_replace($marker, '', $description);
            }
            $entry->billable = true;
            $entry->description = StringHelper::trimToNull($description);
            // Speichern statt still: der Abrechnungs-Snapshot folgt dem Merkmal.
            $entry->save();
        }

        return ['settled' => $settled, 'resolved' => ! $settled && $locked->refresh()->status === ShortfallStatus::Resolved, 'entries' => $entries];
    }

    /**
     * Nachbuchungen, die die falsche Markierung nicht abrechenbar gemacht hat:
     * dieselbe Person, dasselbe Projekt, derselbe Tag, nach der Markierung
     * angelegt, mit Vermerk — und von keiner Rechnung oder Übergabe abgerechnet.
     *
     * @param  list<string>  $markers
     * @return EloquentCollection<int, TimeEntry>
     */
    private function lateEntries(ProjectTimeShortfall $shortfall, ?CarbonInterface $billedAt, array $markers, bool $lock): EloquentCollection {
        $query = TimeEntry::query()
            ->where('organization_id', $shortfall->organization_id)
            ->where('project_id', $shortfall->project_id)
            ->where('user_id', $shortfall->user_id)
            ->whereBetween('date', DateRange::days($shortfall->date, $shortfall->date))
            ->where('billable', false)
            ->where('exported', false)
            ->when($billedAt !== null, static fn ($query) => $query->where('created_at', '>=', $billedAt))
            ->whereNotIn('id', InvoiceItem::query()->whereNotNull('time_entry_id')->select('time_entry_id'))
            ->whereNotExists(static function ($pivot): void {
                $pivot->from('invoice_item_time_entries')->whereColumn('invoice_item_time_entries.time_entry_id', 'time_entries.id');
            })
            ->whereNotIn('id', BillingTransferItem::query()
                ->join('billing_transfers', 'billing_transfers.id', '=', 'billing_transfer_items.billing_transfer_id')
                ->where('billing_transfer_items.source_type', MorphMap::alias(TimeEntry::class))
                ->whereIn('billing_transfers.status', [TransferStatus::Confirmed->value, TransferStatus::Transferred->value])
                ->whereNull('billing_transfers.deleted_at')
                ->select('billing_transfer_items.source_id'))
            ->orderBy('id');
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get()->filter(static function (TimeEntry $entry) use ($markers): bool {
            foreach ($markers as $marker) {
                if (str_contains((string) $entry->description, $marker)) {
                    return true;
                }
            }

            return false;
        })->values();
    }

    /** @param  EloquentCollection<int, TimeEntry>  $entries */
    private function sqids(EloquentCollection $entries): string {
        return $entries->isEmpty() ? 'keine' : $entries->map(static fn (TimeEntry $entry): string => (string) $entry->sqid)->implode(', ');
    }
}
