<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsAuditPackageFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Isms;

use App\Enums\Isms\AuditPackageStatus;
use App\Models\Isms\{IsmsAuditPackage, IsmsScope};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IsmsAuditPackage>
 */
class IsmsAuditPackageFactory extends Factory {
    protected $model = IsmsAuditPackage::class;

    public function definition(): array {
        return [
            'isms_scope_id' => IsmsScope::factory(),
            'package_no' => fake()->unique()->numberBetween(1, 999999),
            'title' => 'Auditpaket ' . fake()->numberBetween(2026, 2030),
            'as_of_date' => now()->toDateString(),
            'norm' => null,
            'edition' => null,
            'status' => AuditPackageStatus::Draft->value,
            'file_path' => null,
            'file_hash' => null,
            'finalized_by_user_id' => null,
            'finalized_at' => null,
            'created_by_user_id' => null,
        ];
    }

    public function status(AuditPackageStatus $status): self {
        return $this->state(fn() => ['status' => $status->value]);
    }
}
