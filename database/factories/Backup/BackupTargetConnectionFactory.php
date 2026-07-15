<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupTargetConnectionFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Backup;

use App\Enums\Backup\{BackupProvider, BackupTargetStatus};
use App\Models\Backup\BackupTargetConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BackupTargetConnection>
 */
class BackupTargetConnectionFactory extends Factory {
    protected $model = BackupTargetConnection::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'provider' => BackupProvider::Dropbox,
            'name' => 'Backupziel ' . fake()->company(),
            'external_account_id' => 'dbid:' . fake()->uuid(),
            'external_account_label' => fake()->companyEmail(),
            'access_token' => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
            'granted_scopes' => ['files.content.write', 'files.content.read'],
            'root_folder_ref' => 'id:' . fake()->lexify('??????????'),
            'status' => BackupTargetStatus::Draft,
        ];
    }

    public function active(): static {
        return $this->state(fn (): array => ['status' => BackupTargetStatus::Active]);
    }
}
