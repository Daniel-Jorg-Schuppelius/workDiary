<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalRevenueSources.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Invoicing\Invoice;
use App\Services\Billing\Contracts\{ExternalRevenue, ExternalRevenueSource};
use App\Services\Billing\Dto\{ExternalInvoiceFile, ExternalInvoiceQuery};
use Carbon\{CarbonImmutable, CarbonInterface};

/**
 * Registry der {@see ExternalRevenueSource}s (MVP-1034): Buchhaltungs-Plugins
 * tragen sich beim Booten ein; Auswertungen lesen die Summe aller Quellen.
 * Summen je Kunde, je Monat und Überfälliges entstehen hier aus den Belegen
 * der Quellen (MVP-1114) — eine Belegregel je Quelle statt einer je Methode.
 */
final class ExternalRevenueSources implements ExternalRevenue {
    /** @var array<string, ExternalRevenueSource> */
    private array $sources = [];

    public function register(ExternalRevenueSource $source): void {
        $this->sources[$source->key()] = $source;
    }

    public function invoices(ExternalInvoiceQuery $query): array {
        $invoices = [];
        foreach ($this->sources as $source) {
            array_push($invoices, ...$source->invoices($query));
        }

        return $invoices;
    }

    public function perCustomer(string $from, string $to, ?int $customerId = null, array $excludedCustomerIds = []): array {
        $totals = [];
        foreach ($this->invoices(new ExternalInvoiceQuery(CarbonImmutable::parse($from), CarbonImmutable::parse($to), $customerId, $excludedCustomerIds)) as $invoice) {
            if ($invoice->customerId === null) {
                continue;
            }
            $totals[$invoice->customerId] ??= ['count' => 0, 'total' => 0.0];
            $totals[$invoice->customerId]['count']++;
            $totals[$invoice->customerId]['total'] += $invoice->gross;
        }

        return $totals;
    }

    public function monthlyRevenue(int $customerId, CarbonInterface $from, CarbonInterface $to): array {
        $months = [];
        foreach ($this->invoices(new ExternalInvoiceQuery($from, $to, $customerId)) as $invoice) {
            if ($invoice->issuedOn === null) {
                continue;
            }
            $month = $invoice->issuedOn->format('Y-m');
            $months[$month] = ($months[$month] ?? 0.0) + $invoice->gross;
        }

        return $months;
    }

    public function overdueCount(int $organizationId, string $today): int {
        $open = $this->invoices(new ExternalInvoiceQuery(withCreditNotes: false, openOnly: true, organizationId: $organizationId));

        return count(array_filter($open, static fn ($invoice): bool => $invoice->dueOn !== null && $invoice->dueOn->toDateString() < $today));
    }

    public function productRevenue(CarbonInterface $from, CarbonInterface $to): array {
        $rows = [];
        foreach ($this->sources as $key => $source) {
            $rows[$key] = $source->productRevenue($from, $to);
        }

        return $rows;
    }

    public function customerIdsWithDocuments(int $organizationId): array {
        $ids = [];
        foreach ($this->sources as $source) {
            array_push($ids, ...$source->customerIdsWithDocuments($organizationId));
        }

        return array_values(array_unique($ids));
    }

    public function file(string $source, int $organizationId, string $key): ?ExternalInvoiceFile {
        foreach ($this->sources as $registered) {
            $file = $registered->file($organizationId, $source, $key);
            if ($file !== null) {
                return $file;
            }
        }

        return null;
    }

    public function fileForInvoice(Invoice $invoice): ?ExternalInvoiceFile {
        foreach ($this->sources as $source) {
            $file = $source->fileForInvoice($invoice);
            if ($file !== null) {
                return $file;
            }
        }

        return null;
    }
}
