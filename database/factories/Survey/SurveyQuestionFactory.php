<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SurveyQuestionFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Survey;

use App\Models\Survey\{Survey, SurveyQuestion};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SurveyQuestion>
 */
class SurveyQuestionFactory extends Factory {
    protected $model = SurveyQuestion::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'organization_id' => null, // wird über Global Scope / explizit gesetzt
            'survey_id' => Survey::factory(),
            'type' => 'nps',
            'label' => 'Wie wahrscheinlich empfehlen Sie uns weiter?',
            'required' => true,
            'position' => 1,
        ];
    }
}
