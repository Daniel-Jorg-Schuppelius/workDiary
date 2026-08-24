<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContractObligationScan.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Notification\DeadlineScans;

use App\Models\{Contract\ContractObligation, Organization};
use App\Services\Contract\ContractService;
use App\Services\Notification\NotificationDispatcher;

/**
 * Allgemeine Vertragsobligationen (Welle D, CLM): Warnung ab Vorwarnzeit
 * + Eskalation; abgelaufene Obligationen laufender Verträge → missed.
 * Logik im ContractService je Organisation. Payload trägt due_at → der
 * Kalender-Kanal (A11) publiziert den Termin automatisch.
 */
class ContractObligationScan extends AbstractDeadlineScan {
    public function __construct(private readonly ContractService $service) {}

    public function key(): string {
        return 'contract_obligations';
    }

    public function run(NotificationDispatcher $dispatcher, DeadlineScanOptions $options): int {
        return $this->sumPerOrganization(
            ContractObligation::query()
                ->withoutGlobalScopes()
                ->where('status', 'open'),
            fn(Organization $organization): int => $this->service->scanObligations($organization),
        );
    }
}
