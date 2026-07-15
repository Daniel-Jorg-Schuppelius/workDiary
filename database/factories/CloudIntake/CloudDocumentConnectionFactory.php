<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CloudDocumentConnectionFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\CloudIntake;

use App\Enums\CloudIntake\{CloudIntakeConnectionStatus, CloudIntakeProvider};
use App\Models\CloudIntake\CloudDocumentConnection;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CloudDocumentConnection>
 */
class CloudDocumentConnectionFactory extends Factory {
    protected $model = CloudDocumentConnection::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'organization_id' => Organization::factory(),
            'provider' => CloudIntakeProvider::Dropbox,
            'name' => 'Dropbox ' . fake()->company(),
            'external_account_id' => 'dbid:' . fake()->uuid(),
            'external_account_label' => fake()->companyEmail(),
            'access_token' => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
            'granted_scopes' => ['files.metadata.read', 'files.content.read'],
            'container_id' => 'root',
            'container_label' => 'Dropbox',
            'root_folder_id' => 'id:' . fake()->lexify('??????????'),
            'root_folder_path' => '/WorkDiary',
            'status' => CloudIntakeConnectionStatus::Draft,
        ];
    }

    public function active(): static {
        return $this->state(fn (): array => ['status' => CloudIntakeConnectionStatus::Active]);
    }
}
