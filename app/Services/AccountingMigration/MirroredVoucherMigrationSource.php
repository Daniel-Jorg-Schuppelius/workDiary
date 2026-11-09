<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MirroredVoucherMigrationSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\AccountingMigration;

use App\Enums\Billing\DocumentKind;
use App\Enums\Migration\MigrationProvider;
use App\Models\Finance\AccountingVoucher;
use App\Services\AccountingMigration\Contracts\MigrationSource;

/**
 * Quelle eines Buchhaltungswechsels aus dem gemeinsamen Belegspiegel
 * `accounting_vouchers` (MVP-1506): sevDesk und easybill tragen sich mit ihrem
 * Fall ein; die Plugin-ID ist der Wert des Falls. Serienvorlagen sind Pläne,
 * keine Belege; im Programm gelöschte Belege sind Historie, nicht offen.
 */
final class MirroredVoucherMigrationSource implements MigrationSource {
    public function __construct(private readonly MigrationProvider $provider) {}

    public function provider(): MigrationProvider {
        return $this->provider;
    }

    public function documents(int $organizationId, array $settledStates): iterable {
        foreach (AccountingVoucher::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('plugin_id', $this->provider->value)
            ->where(static fn ($q) => $q->whereNull('document_kind')->orWhere('document_kind', '!=', DocumentKind::RecurringTemplate->value))
            ->orderBy('id')
            ->cursor() as $voucher) {
            $state = $voucher->voucher_state?->value;
            yield [
                'external_id' => (string) $voucher->external_id,
                'number' => $voucher->voucher_number,
                'status' => $state,
                'date' => $voucher->voucher_date?->toDateString(),
                'open_amount' => $voucher->open_amount !== null ? (float) $voucher->open_amount : null,
                'is_open' => ! (bool) $voucher->archived && $voucher->source_deleted_at === null && ! in_array($state, $settledStates, true),
            ];
        }
    }

    public function documentMorphClass(): string {
        return (new AccountingVoucher)->getMorphClass();
    }

    public function completionBlockers(int $organizationId): array {
        return [];
    }
}
