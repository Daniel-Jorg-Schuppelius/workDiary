<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsNormStatusFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Isms;

use App\Enums\Isms\NormConformityStatus;
use App\Models\Isms\{IsmsNormStatus, IsmsScope};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IsmsNormStatus>
 */
class IsmsNormStatusFactory extends Factory {
    protected $model = IsmsNormStatus::class;

    public function definition(): array {
        return [
            'isms_scope_id' => IsmsScope::factory(),
            'norm' => 'ISO/IEC 27001',
            'edition' => '2022',
            'status' => NormConformityStatus::NotAssessed->value,
            'notes' => null,
        ];
    }

    public function status(NormConformityStatus $status): self {
        return $this->state(fn() => ['status' => $status->value]);
    }

    public function certified(): self {
        return $this->status(NormConformityStatus::Certified);
    }
}
