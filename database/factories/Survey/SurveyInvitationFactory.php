<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SurveyInvitationFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Survey;

use App\Models\Survey\{Survey, SurveyInvitation};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SurveyInvitation>
 */
class SurveyInvitationFactory extends Factory {
    protected $model = SurveyInvitation::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'organization_id' => null, // wird über Global Scope / explizit gesetzt
            'survey_id' => Survey::factory(),
            'email' => fake()->unique()->safeEmail(),
            'context_kind' => 'manual',
            // Klartext-Token wird nie gespeichert — der Hash genügt (sha512 = 128 Zeichen).
            'token_hash' => hash('sha512', fake()->unique()->uuid()),
            'expires_at' => now()->addDays(14),
            'status' => SurveyInvitation::STATUS_CREATED,
        ];
    }
}
