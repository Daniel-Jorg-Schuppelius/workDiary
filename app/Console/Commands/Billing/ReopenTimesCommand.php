<?php
/*
 * Created on   : Sun Aug 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReopenTimesCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands\Billing;

use App\Console\Concerns\IteratesOrganizations;
use App\Models\{Customer, Organization, TimeEntry};
use App\Support\Sqid;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Gegenstück zum Altbestand-Abschluss („Als abgerechnet markieren", MVP-472):
 * öffnet versehentlich geschlossene Zeiten wieder, damit sie erneut in
 * „Offene Zeiten" und in der Fakturierung auftauchen.
 *
 * Bewusst eng geführt — der `exported`-Flag ist der Verbraucht-Nachweis aller
 * Abrechnungspfade. Nie angefasst werden Zeiten, die
 *  - an einer Rechnung hängen (Pivot `invoice_item_time_entries` oder die
 *    Einzel-FK `invoice_items.time_entry_id`),
 *  - in einer bestätigten/übergebenen Faktura-Übergabe stecken,
 *  - zu einem saldo-geführten Kunden gehören (dort setzt der Monatsabschluss
 *    `exported`; ein Rückholen würde den Saldo verfälschen).
 *
 * Ohne --apply nur Dry-Run. Der Lauf wird als `time_entries.reopened`
 * auditiert (Anzahl + Filter, keine Einzelwerte) — die ursprüngliche
 * Massenaktion hinterlässt keine Spur, die Korrektur schon.
 */
class ReopenTimesCommand extends Command {
    use IteratesOrganizations;

    protected $signature = 'billing:reopen-times '
        . self::ORGANIZATION_OPTION
        . ' {--from= : Leistungsdatum ab (YYYY-MM-DD), Pflicht}'
        . ' {--to= : Leistungsdatum bis (YYYY-MM-DD)}'
        . ' {--customer= : Kundennummer, Sqid oder ID eines einzelnen Kunden}'
        . ' {--apply : Änderungen schreiben (sonst Dry-Run)}';

    protected $description = 'Öffnet fälschlich als abgerechnet markierte Zeiten ab einem Leistungsdatum wieder. Rechnungs-, Übergabe- und saldo-geführte Zeiten bleiben unangetastet. Ohne --apply nur Dry-Run.';

    public function handle(): int {
        $from = trim((string) $this->option('from'));
        if ($from === '') {
            $this->error('--from ist Pflicht (Leistungsdatum ab, z. B. --from=2026-04-01).');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');

        $failures = $this->forEachOrganization(function (Organization $org) use ($from, $apply): void {
            $customerId = $this->customerId();
            if ($this->option('customer') !== null && $this->option('customer') !== '' && $customerId === null) {
                $this->warn(sprintf('Organisation #%d (%s): Kunde nicht gefunden — übersprungen.', $org->id, $org->name));

                return;
            }

            $query = $this->query($from, $customerId);
            $total = (clone $query)->count();

            if ($total === 0) {
                $this->line(sprintf('Organisation #%d (%s): nichts zu öffnen.', $org->id, $org->name));

                return;
            }

            // Übersicht je Kunde, damit vor dem Schreiben sichtbar ist, wen es trifft.
            foreach ($this->byCustomer($from, $customerId) as $row) {
                $this->line(sprintf(
                    '  %s: %d Einträge (%s – %s)',
                    $row['customer_name'],
                    $row['entries'],
                    $row['first_date'],
                    $row['last_date'],
                ));
            }

            if ($apply) {
                // Mass-Update ohne Model-Events — symmetrisch zum Abschluss und
                // ohne Rückschreibung an Toggl & Co. auszulösen.
                (clone $query)->update(['exported' => false]);

                // Auditiert an der Organisation (auditable ist nicht nullable);
                // die Einzelwerte stehen bewusst nicht drin — nur Umfang und Filter.
                $org->audit('time_entries.reopened', [
                    'count' => $total,
                    'from' => $from,
                    'to' => trim((string) $this->option('to')) ?: null,
                    'customer_id' => $customerId,
                ]);
            }

            $this->info(sprintf(
                'Organisation #%d (%s): %d Einträge %s.',
                $org->id,
                $org->name,
                $total,
                $apply ? 'wieder geöffnet' : 'würden geöffnet (Dry-Run, --apply zum Schreiben)',
            ));
        });

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @return Builder<TimeEntry> */
    private function query(string $from, ?int $customerId): Builder {
        $to = trim((string) $this->option('to'));

        return TimeEntry::query()
            ->where('exported', true)
            ->whereDate('date', '>=', $from)
            ->when($to !== '', fn(Builder $q) => $q->whereDate('date', '<=', $to))
            ->when($customerId !== null, fn(Builder $q) => $q->whereHas('project', fn(Builder $p) => $p->where('customer_id', $customerId)))
            // Saldo-geführte Kunden: dort ist `exported` das Ergebnis des
            // Monatsabschlusses, kein Abrechnungs-Versehen.
            ->withoutLedgerManagedCustomers()
            // An einer Rechnung hängende Zeiten bleiben verbraucht.
            ->whereNotExists(fn($sub) => $sub->selectRaw('1')
                ->from('invoice_item_time_entries')
                ->whereColumn('invoice_item_time_entries.time_entry_id', 'time_entries.id'))
            ->whereNotExists(fn($sub) => $sub->selectRaw('1')
                ->from('invoice_items')
                ->whereColumn('invoice_items.time_entry_id', 'time_entries.id'))
            // Ebenso Zeiten, die in einer bestätigten/übergebenen Übergabe stecken.
            ->whereNotExists(fn($sub) => $sub->selectRaw('1')
                ->from('billing_transfer_items')
                ->join('billing_transfers', 'billing_transfers.id', '=', 'billing_transfer_items.billing_transfer_id')
                ->whereColumn('billing_transfer_items.source_id', 'time_entries.id')
                ->where('billing_transfer_items.source_type', TimeEntry::class)
                ->whereIn('billing_transfers.status', ['confirmed', 'transferred']));
    }

    /**
     * Betroffene Einträge je Kunde — Grundlage der Dry-Run-Ausgabe.
     *
     * @return array<int, array{customer_name: string, entries: int, first_date: string, last_date: string}>
     */
    private function byCustomer(string $from, ?int $customerId): array {
        $rows = $this->query($from, $customerId)
            ->leftJoin('projects', 'projects.id', '=', 'time_entries.project_id')
            ->leftJoin('customers', 'customers.id', '=', 'projects.customer_id')
            ->reorder()
            ->groupBy('customers.id', 'customers.name')
            ->selectRaw('COALESCE(customers.name, ?) as customer_name, COUNT(*) as entries, MIN(time_entries.date) as first_date, MAX(time_entries.date) as last_date', [(string) __('finance.field.source_deleted')])
            ->orderByDesc('entries')
            ->get();

        return $rows->map(static fn(TimeEntry $row): array => [
            'customer_name' => (string) $row->getAttribute('customer_name'),
            'entries' => (int) $row->getAttribute('entries'),
            'first_date' => (string) $row->getAttribute('first_date'),
            'last_date' => (string) $row->getAttribute('last_date'),
        ])->values()->all();
    }

    /**
     * Kunden-Option als Kundennummer, Sqid oder interne ID; null = alle Kunden
     * der Organisation. Die Kundennummer gewinnt bewusst vor der numerischen
     * ID: sie ist der im UI sichtbare Wert — eine rein numerische Kundennummer
     * darf nicht als interne ID eines anderen Kunden fehlinterpretiert werden.
     */
    private function customerId(): ?int {
        $value = trim((string) ($this->option('customer') ?? ''));
        if ($value === '') {
            return null;
        }

        $byNumber = Customer::query()->where('number', $value)->value('id');
        if ($byNumber !== null) {
            return (int) $byNumber;
        }

        $id = Sqid::decodeOrNumeric(Customer::class, $value);

        return $id !== null && Customer::query()->whereKey($id)->exists() ? $id : null;
    }
}
