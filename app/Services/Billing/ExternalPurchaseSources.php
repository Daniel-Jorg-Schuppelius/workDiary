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
use Carbon\CarbonInterface;

/** Registry der {@see ExternalPurchaseSource}s (MVP-1036); Plugins tragen sich beim Booten ein. */
final class ExternalPurchaseSources implements ExternalPurchases {
    /** @var array<string, ExternalPurchaseSource> */
    private array $sources = [];

    public function register(ExternalPurchaseSource $source): void {
        $this->sources[$source->key()] = $source;
    }

    public function purchases(?CarbonInterface $from, ?CarbonInterface $to, ?array $supplierIds = null): array {
        $purchases = [];
        foreach ($this->sources as $source) {
            array_push($purchases, ...$source->purchases($from, $to, $supplierIds));
        }

        return $purchases;
    }

    public function categorySpend(CarbonInterface $from, CarbonInterface $to): array {
        $rows = [];
        $pending = 0;
        foreach ($this->sources as $source) {
            $spend = $source->categorySpend($from, $to);
            array_push($rows, ...$spend['rows']);
            $pending += $spend['pending'];
        }

        return ['rows' => $rows, 'pending' => $pending];
    }
}
