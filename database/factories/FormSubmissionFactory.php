<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FormSubmissionFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Models\{FormSubmission, FormTemplate, User};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormSubmission>
 */
class FormSubmissionFactory extends Factory {
    protected $model = FormSubmission::class;

    public function definition(): array {
        return [
            'form_template_id' => FormTemplate::factory(),
            'fields_snapshot' => FormTemplateFactory::sampleFields(),
            'values' => [
                'bemerkung' => fake()->sentence(3),
                'beschreibung' => null,
                'messwert' => 42.5,
                'datum' => now()->toDateString(),
                'zustand' => 'gut',
                'geprueft' => true,
            ],
            'subject_type' => null,
            'subject_id' => null,
            'submitted_by_user_id' => User::factory(),
            'submitted_at' => now(),
        ];
    }
}
