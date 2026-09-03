<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PriceCheckRow.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Marketplace;

use App\Enums\Reselling\BillingFrequency;
use CommonToolkit\ValueObjects\Money;

/**
 * Preisprüfung je Produkt: Einkauf laut Vertrag und laut Preisliste, UVP und
 * die tatsächlich berechneten Verkaufspreise je Stück aus den Rechnungen.
 */
final readonly class PriceCheckRow {
    public const FLAG_BELOW_LIST = 'below_list';

    public const FLAG_BELOW_UVP = 'below_uvp';

    public const FLAG_CONTRACT_ABOVE_LIST = 'contract_above_list';

    public const FLAG_NO_SALES = 'no_sales';

    public const FLAG_NO_LIST = 'no_list';

    /**
     * @param  list<string>  $flags
     */
    public function __construct(
        public string $product,
        public int $termMonths,
        public BillingFrequency $interval,
        public int $runningQuantity,
        public ?Money $contractUnitMin,
        public ?Money $contractUnitMax,
        public ?Money $listPrice,
        public ?Money $uvp,
        public ?Money $salesMin,
        public ?Money $salesMedian,
        public ?Money $salesMax,
        public int $salesSamples,
        public ?float $marginPercent,
        public array $flags,
    ) {}
}
