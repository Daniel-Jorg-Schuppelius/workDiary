<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupGenerationFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Backup;

use App\Enums\Backup\{BackupGenerationStatus, BackupRetentionClass};
use App\Models\Backup\{BackupGeneration, BackupTargetConnection};
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BackupGeneration>
 */
class BackupGenerationFactory extends Factory {
    protected $model = BackupGeneration::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'connection_id' => BackupTargetConnection::factory(),
            'snapshot_uuid' => (string) Str::uuid(),
            'retention_class' => BackupRetentionClass::Daily,
            'status' => BackupGenerationStatus::Building,
            'remote_prefix' => 'wd-' . fake()->lexify('????????') . '/' . fake()->uuid(),
            'app_version' => '1.0.0-test',
            'legal_hold' => false,
            'started_at' => now(),
        ];
    }

    public function committed(): static {
        return $this->state(fn (): array => [
            'status' => BackupGenerationStatus::Committed,
            'plain_size' => 4096,
            'cipher_size' => 4300,
            'part_count' => 1,
            'manifest_sha256' => hash('sha256', fake()->uuid()),
            'key_envelope' => base64_encode(random_bytes(72)),
            'committed_at' => now(),
        ]);
    }

    public function verified(): static {
        return $this->committed()->state(fn (): array => [
            'status' => BackupGenerationStatus::Verified,
            'last_verified_at' => now(),
        ]);
    }
}
