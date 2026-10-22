<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecalculateAccountStatements.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Jobs\Billing;

use App\Models\Billing\CustomerBillingAgreement;
use App\Models\Platform\Organization;
use App\Services\Billing\CustomerAccountStatementService;
use App\Support\OrganizationContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\{ShouldBeUnique, ShouldQueue};
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};

/**
 * Monatskette eines Kundenkontos nachrechnen (Vollscan 2026-10-21, lk-13):
 * je Vereinbarung höchstens ein wartender Lauf, auch bei Massenimporten.
 */
final class RecalculateAccountStatements implements ShouldBeUnique, ShouldQueue {
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $uniqueFor = 300;

    public function __construct(public readonly int $agreementId) {}

    public function uniqueId(): string {
        return (string) $this->agreementId;
    }

    public function handle(CustomerAccountStatementService $statements): void {
        $agreement = CustomerBillingAgreement::query()->withoutGlobalScopes()->find($this->agreementId);
        if (! $agreement instanceof CustomerBillingAgreement || ! $agreement->keepsLedger()) {
            return;
        }
        $organization = Organization::query()->find($agreement->organization_id);
        if ($organization instanceof Organization) {
            OrganizationContext::run($organization, static fn () => $statements->recalculateOpen($agreement));
        }
    }
}
