<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingMigrationItemFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Migration;

use App\Enums\Migration\MigrationDataArea;
use App\Models\Migration\{AccountingMigrationItem, AccountingMigrationRun};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountingMigrationItem>
 */
class AccountingMigrationItemFactory extends Factory {
    protected $model = AccountingMigrationItem::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'organization_id' => null, // wird über Global Scope / explizit gesetzt
            'accounting_migration_run_id' => AccountingMigrationRun::factory(),
            'data_area' => MigrationDataArea::Customers,
            'status' => AccountingMigrationItem::STATUS_PENDING,
            'dedupe_key' => 'customers:' . fake()->unique()->uuid(),
            'display_title' => fake()->company(),
        ];
    }
}
