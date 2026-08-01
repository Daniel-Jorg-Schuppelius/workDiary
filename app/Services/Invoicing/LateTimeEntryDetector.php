<?php
/*
 * Created on   : Fri Aug 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LateTimeEntryDetector.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Invoicing;

use App\Models\{Customer, Invoice, InvoiceItem, Project, TimeEntry};
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Nachzügler-Erkennung (MVP-461): offene Zeiteinträge, deren Leistungsdatum in
 * einen bereits abgerechneten Zeitraum des Kunden fällt — sie wurden nach dem
 * Rechnungslauf erfasst oder beim Lauf übersehen und würden ohne erneuten Lauf
 * mit passendem Datumsfenster still liegenbleiben. Pendant zum Kontomodus
 * ({@see \App\Services\Billing\CustomerAccountStatementService} strayEntries)
 * für den normalen Rechnungspfad.
 */
class LateTimeEntryDetector {
    /**
     * Jüngstes fakturiertes Leistungsdatum des Kunden (optional projektgenau):
     * max(invoice_items.service_date) über Rechnungen, die weder Entwurf noch
     * storniert sind. NULL, wenn noch nie fakturiert wurde.
     */
    public function latestBilledServiceDate(Customer $customer, ?Project $project = null): ?CarbonImmutable {
        $query = InvoiceItem::query()
            ->whereNotNull('service_date')
            ->whereHas('invoice', function (Builder $q) use ($customer): void {
                $q->where('customer_id', $customer->id)
                    ->whereNotIn('status', [Invoice::STATUS_DRAFT, Invoice::STATUS_CANCELLED]);
            });

        if ($project !== null) {
            $projectId = $project->id;
            // Projektbezug läuft über die Quell-Zeiteinträge (Direkt-FK
            // erster Blockeintrag ODER Pivot der gebündelten Einträge).
            $query->where(function (Builder $inner) use ($projectId): void {
                $inner->whereHas('timeEntry', fn(Builder $t) => $t->where('project_id', $projectId))
                    ->orWhereHas('timeEntries', fn(Builder $t) => $t->where('project_id', $projectId));
            });
        }

        $max = $query->max('service_date');

        return is_string($max) && $max !== '' ? CarbonImmutable::parse($max) : null;
    }

    /**
     * Jüngstes fakturiertes Leistungsdatum je Kunde (eine Query) — für die
     * Badge-Markierung der Arbeitsliste.
     *
     * @param  list<int>  $customerIds
     * @return array<int, CarbonImmutable>
     */
    public function latestBilledServiceDates(array $customerIds): array {
        if ($customerIds === []) {
            return [];
        }

        $rows = InvoiceItem::query()
            ->selectRaw('invoices.customer_id as customer_id, MAX(invoice_items.service_date) as latest')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->whereIn('invoices.customer_id', $customerIds)
            ->whereNotIn('invoices.status', [Invoice::STATUS_DRAFT, Invoice::STATUS_CANCELLED])
            ->whereNotNull('invoice_items.service_date')
            ->groupBy('invoices.customer_id')
            ->pluck('latest', 'customer_id');

        $result = [];
        foreach ($rows as $customerId => $latest) {
            if (is_string($latest) && $latest !== '') {
                $result[(int) $customerId] = CarbonImmutable::parse($latest);
            }
        }

        return $result;
    }

    /**
     * Filtert aus den übergebenen offenen Einträgen die Nachzügler heraus
     * (Leistungsdatum <= jüngstes fakturiertes Leistungsdatum des Kunden).
     *
     * @param  Collection<int, TimeEntry>  $openEntries
     * @return Collection<int, TimeEntry>
     */
    public function detect(Collection $openEntries, Customer $customer, ?Project $project = null): Collection {
        $latest = $this->latestBilledServiceDate($customer, $project);
        if ($latest === null) {
            return $openEntries->take(0);
        }

        return $openEntries->filter(
            fn(TimeEntry $entry): bool => $entry->date !== null && $entry->date->lte($latest)
        )->values();
    }

    /**
     * Anzahl der Nachzügler je Kunde über der gesamten offenen Menge —
     * für die KPI der Arbeitsliste. Ein Eintrag zählt, wenn sein Datum vor
     * dem jüngsten fakturierten Leistungsdatum seines Kunden liegt.
     *
     * @param  Builder<TimeEntry>  $openQuery  Query über offene Einträge (exported=false).
     */
    public function countLateInQuery(Builder $openQuery): int {
        return (clone $openQuery)
            ->reorder()
            ->whereNotNull('time_entries.date')
            ->whereExists(function ($sub): void {
                $sub->selectRaw('1')
                    ->from('invoice_items')
                    ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
                    ->join('projects as late_projects', 'late_projects.id', '=', 'time_entries.project_id')
                    ->whereColumn('invoices.customer_id', 'late_projects.customer_id')
                    ->whereNotIn('invoices.status', [Invoice::STATUS_DRAFT, Invoice::STATUS_CANCELLED])
                    ->whereNotNull('invoice_items.service_date')
                    ->whereColumn('invoice_items.service_date', '>=', 'time_entries.date');
            })
            ->count();
    }
}
