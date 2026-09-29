<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PartyDocument.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing\Dto;

use App\Support\Ui\UiAction;
use Carbon\CarbonInterface;

final readonly class PartyDocument {
    /**
     * @param  string  $type  Belegart; Anzeige über `values.<type>`
     * @param  ?string  $status  Status; Anzeige über `values.<status>`
     * @param  list<UiAction>  $actions
     */
    public function __construct(
        public string $type,
        public ?string $number,
        public ?CarbonInterface $date,
        public ?string $status,
        public float $amount,
        public string $currency,
        public array $actions = [],
    ) {}
}
