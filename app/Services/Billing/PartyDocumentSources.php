<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PartyDocumentSources.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Customer\Customer;
use App\Models\Supplier\Supplier;
use App\Services\Billing\Contracts\PartyDocumentSource;
use App\Services\Billing\Dto\PartyDocumentList;
use Carbon\CarbonInterface;

final class PartyDocumentSources {
    /** @var array<string, PartyDocumentSource> */
    private array $sources = [];

    public function register(PartyDocumentSource $source): void {
        $this->sources[$source->key()] = $source;
    }

    /** @return list<PartyDocumentList> aktive Quellen */
    public function forParty(Customer|Supplier $party, CarbonInterface $from, CarbonInterface $to): array {
        $lists = [];
        foreach ($this->sources as $source) {
            $list = $source->documentsFor($party, $from, $to);
            if ($list !== null) {
                $lists[] = $list;
            }
        }

        return $lists;
    }
}
