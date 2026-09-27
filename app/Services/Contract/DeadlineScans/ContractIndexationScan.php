<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContractIndexationScan.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Contract\DeadlineScans;

use App\Enums\Contract\IndexationMethod;
use App\Models\Contract\Contract;
use App\Models\Platform\Organization;
use App\Services\Contract\ContractIndexationService;
use App\Services\Notification\DeadlineScans\{AbstractDeadlineScan, DeadlineScanOptions};
use App\Services\Notification\NotificationDispatcher;

/** Anpassungsvorschläge nach freigegebenem VPI-Stand (MVP-952). */
class ContractIndexationScan extends AbstractDeadlineScan {
    public function __construct(private readonly ContractIndexationService $service) {}

    public function key(): string {
        return 'contract_indexations';
    }

    public function run(NotificationDispatcher $dispatcher, DeadlineScanOptions $options): int {
        return $this->sumPerOrganization(
            Contract::query()->withoutGlobalScopes()
                ->where('indexation_method', IndexationMethod::ConsumerPriceIndex->value)
                ->whereNotNull('indexation_base_value'),
            fn (Organization $organization): int => $this->service->scan($organization),
        );
    }
}
