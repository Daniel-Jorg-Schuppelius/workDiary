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

use App\Services\Billing\Contracts\{ExternalRevenue, ExternalRevenueSource};
use Carbon\CarbonInterface;

/**
 * Registry der {@see ExternalRevenueSource}s (MVP-1034): Buchhaltungs-Plugins
 * tragen sich beim Booten ein; Auswertungen lesen die Summe aller Quellen.
 */
final class ExternalRevenueSources implements ExternalRevenue {
    /** @var array<string, ExternalRevenueSource> */
    private array $sources = [];

    public function register(ExternalRevenueSource $source): void {
        $this->sources[$source->key()] = $source;
    }

    public function invoiceRows(string $upTo, ?int $customerId = null, array $excludedCustomerIds = []): array {
        $rows = [];
        foreach ($this->sources as $source) {
            array_push($rows, ...$source->invoiceRows($upTo, $customerId, $excludedCustomerIds));
        }

        return $rows;
    }

    public function perCustomer(string $from, string $to, ?int $customerId = null, array $excludedCustomerIds = []): array {
        $totals = [];
        foreach ($this->sources as $source) {
            foreach ($source->perCustomer($from, $to, $customerId, $excludedCustomerIds) as $id => $aggregate) {
                $totals[$id] ??= ['count' => 0, 'total' => 0.0];
                $totals[$id]['count'] += $aggregate['count'];
                $totals[$id]['total'] += $aggregate['total'];
            }
        }

        return $totals;
    }

    public function monthlyRevenue(int $customerId, CarbonInterface $from, CarbonInterface $to): array {
        $months = [];
        foreach ($this->sources as $source) {
            foreach ($source->monthlyRevenue($customerId, $from, $to) as $month => $amount) {
                $months[$month] = ($months[$month] ?? 0.0) + $amount;
            }
        }

        return $months;
    }

    public function overdueCount(int $organizationId, string $today): int {
        $count = 0;
        foreach ($this->sources as $source) {
            $count += $source->overdueCount($organizationId, $today);
        }

        return $count;
    }

    public function productRevenue(CarbonInterface $from, CarbonInterface $to): array {
        $rows = [];
        foreach ($this->sources as $key => $source) {
            $rows[$key] = $source->productRevenue($from, $to);
        }

        return $rows;
    }
}
