<?php
/*
 * Created on   : Wed Sep 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeMigrationSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Lexoffice\Services;

use App\Enums\Migration\MigrationProvider;
use App\Plugins\Lexoffice\Models\LexofficeVoucher;
use App\Services\AccountingMigration\Contracts\MigrationSource;

/** Lexoffice als Quelle eines Buchhaltungswechsels: eigener Belegspiegel `lexoffice_vouchers`. */
final class LexofficeMigrationSource implements MigrationSource {
    public function provider(): MigrationProvider {
        return MigrationProvider::Lexoffice;
    }

    public function documents(int $organizationId, array $settledStates): iterable {
        foreach (LexofficeVoucher::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->orderBy('id')
            ->cursor() as $voucher) {
            $status = (string) $voucher->voucher_status;
            yield [
                'external_id' => (string) $voucher->external_id,
                'number' => $voucher->voucher_number,
                'status' => $status,
                'date' => $voucher->voucher_date?->toDateString(),
                'open_amount' => $voucher->open_amount?->toFloat(),
                'is_open' => ! (bool) $voucher->archived && ! in_array($status, $settledStates, true),
            ];
        }
    }

    public function documentMorphClass(): string {
        return (new LexofficeVoucher)->getMorphClass();
    }

    /** Belegbilder MÜSSEN vor dem Abschluss lokal gesichert sein — nach Vertragsende ist die API weg (GoBD, MVP-690). */
    public function completionBlockers(int $organizationId): array {
        $unmaterialized = LexofficeVoucher::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->whereNull('file_materialized_at')
            ->count();

        return $unmaterialized > 0
            ? [(string) __(':n Lexoffice-Belegbilder sind noch nicht lokal gesichert (lexoffice:materialize-voucher-files).', ['n' => $unmaterialized])]
            : [];
    }
}
