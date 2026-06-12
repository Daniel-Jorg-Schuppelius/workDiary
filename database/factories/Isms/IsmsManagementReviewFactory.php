<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsManagementReviewFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Isms;

use App\Enums\Isms\ReviewStatus;
use App\Models\Isms\{IsmsManagementReview, IsmsScope};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IsmsManagementReview>
 */
class IsmsManagementReviewFactory extends Factory {
    protected $model = IsmsManagementReview::class;

    public function definition(): array {
        return [
            'isms_scope_id' => IsmsScope::factory(),
            'review_no' => fake()->unique()->numberBetween(1, 999999),
            'held_on' => now()->subWeek()->toDateString(),
            'participants' => 'Geschäftsführung, ISB',
            'inputs' => 'Auditergebnisse, Kennzahlen, Risikolage.',
            'decisions' => 'Ressourcen für Maßnahmenumsetzung freigegeben.',
            'follow_ups' => null,
            'status' => ReviewStatus::Draft->value,
            'approved_by_user_id' => null,
            'approved_at' => null,
        ];
    }

    public function approved(?int $approvedByUserId = null): self {
        return $this->state(fn() => [
            'status' => ReviewStatus::Approved->value,
            'approved_by_user_id' => $approvedByUserId,
            'approved_at' => now(),
        ]);
    }
}
