<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalPurchaseSources.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing;

use App\Services\Billing\Contracts\{ExternalPurchaseSource, ExternalPurchases};
use App\Services\Billing\Dto\{ExternalCategorySpend, ExternalPurchase};
use Carbon\CarbonInterface;

/** Registry der {@see ExternalPurchaseSource}s (MVP-1036); Plugins tragen sich beim Booten ein. */
final class ExternalPurchaseSources implements ExternalPurchases {
    /** @var array<string, ExternalPurchaseSource> */
    private array $sources = [];

    public function register(ExternalPurchaseSource $source): void {
        $this->sources[$source->key()] = $source;
    }

    public function purchases(?CarbonInterface $from, ?CarbonInterface $to, ?array $supplierIds = null, array $exceptSupplierIds = []): array {
        $purchases = [];
        foreach ($this->sources as $source) {
            array_push($purchases, ...$source->purchases($from, $to, $supplierIds));
        }

        return $this->withoutSuppliers($purchases, $exceptSupplierIds);
    }

    public function categorySpend(CarbonInterface $from, CarbonInterface $to, array $exceptSupplierIds = []): array {
        $rows = [];
        $pending = 0;
        foreach ($this->sources as $source) {
            $spend = $source->categorySpend($from, $to);
            array_push($rows, ...$spend['rows']);
            $pending += $spend['pending'];
        }

        return ['rows' => $this->withoutSuppliers($rows, $exceptSupplierIds), 'pending' => $pending];
    }

    public function openPayables(int $organizationId, string $today): array {
        $total = ['open' => 0.0, 'overdue' => 0.0, 'count' => 0];
        foreach ($this->sources as $source) {
            $payables = $source->openPayables($organizationId, $today);
            $total['open'] += $payables['open'];
            $total['overdue'] += $payables['overdue'];
            $total['count'] += $payables['count'];
        }

        return $total;
    }

    /**
     * @template T of ExternalPurchase|ExternalCategorySpend
     *
     * @param  list<T>  $rows
     * @param  list<int>  $exceptSupplierIds
     * @return list<T>
     */
    private function withoutSuppliers(array $rows, array $exceptSupplierIds): array {
        if ($exceptSupplierIds === []) {
            return $rows;
        }
        $except = array_flip($exceptSupplierIds);

        return array_values(array_filter($rows, static fn(ExternalPurchase|ExternalCategorySpend $row): bool => ! isset($except[$row->supplierId])));
    }
}
