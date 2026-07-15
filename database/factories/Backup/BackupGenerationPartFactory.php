<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupGenerationPartFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Backup;

use App\Models\Backup\{BackupGeneration, BackupGenerationPart};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BackupGenerationPart>
 */
class BackupGenerationPartFactory extends Factory {
    protected $model = BackupGenerationPart::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'generation_id' => BackupGeneration::factory(),
            'part_no' => 1,
            'plain_size' => 4096,
            'cipher_size' => 4300,
            'plain_sha256' => hash('sha256', fake()->uuid()),
            'cipher_sha256' => hash('sha256', fake()->uuid()),
        ];
    }

    public function uploaded(): static {
        return $this->state(fn (): array => [
            'remote_ref' => 'id:' . fake()->lexify('??????????'),
            'uploaded_at' => now(),
        ]);
    }
}
