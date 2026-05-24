<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureStepDefFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\Procedure\ProcedureStepType;
use App\Models\{ProcedureStepDef, ProcedureTemplateVersion};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProcedureStepDef>
 */
class ProcedureStepDefFactory extends Factory {
    protected $model = ProcedureStepDef::class;

    public function definition(): array {
        return [
            'procedure_template_version_id' => ProcedureTemplateVersion::factory(),
            'sort_order' => 10,
            'code' => 'step_' . fake()->unique()->numerify('###'),
            'step_type' => ProcedureStepType::Confirm->value,
            'label' => fake()->sentence(3),
            'description' => null,
            'required' => true,
            'blocking' => true,
            'config' => null,
            'required_role' => null,
            'required_qualification_code' => null,
            'requires_second_person' => false,
            'requires_proof_type' => null,
        ];
    }
}
