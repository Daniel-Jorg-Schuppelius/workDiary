<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingMigrationRunFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Migration;

use App\Enums\Migration\{AccountingMigrationStatus, MigrationDataArea, MigrationProvider};
use App\Models\Migration\AccountingMigrationRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountingMigrationRun>
 */
class AccountingMigrationRunFactory extends Factory {
    protected $model = AccountingMigrationRun::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'organization_id' => null, // wird über Global Scope / explizit gesetzt
            'source_plugin' => MigrationProvider::Lexoffice->value,
            'target_plugin' => MigrationProvider::OrgaMax->value,
            'status' => AccountingMigrationStatus::Draft,
            'data_areas' => [MigrationDataArea::Customers->value],
            'dry_run_only' => true,
        ];
    }
}
