<?php

/*
 * Created on   : Wed Jun 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClassificationRequirementFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\Classification\{ClassificationRequirementPhase, ClassificationRequirementSeverity};
use App\Models\{ClassificationRequirement, Organization};
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ClassificationRequirement> */
class ClassificationRequirementFactory extends Factory {
    protected $model = ClassificationRequirement::class;

    public function definition(): array {
        return [
            'organization_id' => Organization::factory(),
            'entry_type_code' => 'service',
            'required_domain' => 'defect_type',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'allow_multi' => false,
            'min_count' => 1,
            'max_count' => null,
            'only_if_json' => null,
            'note' => null,
        ];
    }
}
