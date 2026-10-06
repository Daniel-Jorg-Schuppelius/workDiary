<?php
/*
 * Created on   : Wed Sep 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrgaMaxMigrationSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\OrgaMax\Services;

use App\Enums\Migration\MigrationProvider;
use App\Plugins\OrgaMax\Enums\OrgaMaxInvoiceStatus;
use App\Plugins\OrgaMax\Models\OrgaMaxInvoice;
use App\Services\AccountingMigration\Contracts\MigrationSource;

/** orgaMAX als Quelle eines Buchhaltungswechsels: Rechnungsprojektion `orgamax_invoices`. */
final class OrgaMaxMigrationSource implements MigrationSource {
    public function provider(): MigrationProvider {
        return MigrationProvider::OrgaMax;
    }

    public function documents(int $organizationId, array $settledStates): iterable {
        foreach (OrgaMaxInvoice::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->orderBy('id')
            ->cursor() as $invoice) {
            $status = ($invoice->invoice_status ?? OrgaMaxInvoiceStatus::Unknown)->value;
            yield [
                'external_id' => (string) $invoice->external_id,
                'number' => $invoice->invoice_number,
                'status' => $status,
                'date' => $invoice->invoice_date?->toDateString(),
                'open_amount' => $invoice->outstanding_amount?->toFloat(),
                'is_open' => ! in_array($status, $settledStates, true),
            ];
        }
    }

    public function documentMorphClass(): string {
        return (new OrgaMaxInvoice)->getMorphClass();
    }

    public function completionBlockers(int $organizationId): array {
        return [];
    }
}
