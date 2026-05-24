<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureTemplateVersionFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\Procedure\ProcedureRiskLevel;
use App\Models\{ProcedureTemplate, ProcedureTemplateVersion, User};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProcedureTemplateVersion>
 */
class ProcedureTemplateVersionFactory extends Factory {
    protected $model = ProcedureTemplateVersion::class;

    public function definition(): array {
        return [
            'procedure_template_id' => ProcedureTemplate::factory(),
            'version' => 1,
            'valid_from' => null,
            'valid_to' => null,
            'change_note' => null,
            'published_at' => null,
            'published_by_user_id' => null,
            'risk_level' => ProcedureRiskLevel::Normal->value,
            'applicability' => null,
        ];
    }

    public function published(): self {
        return $this->state(fn() => [
            'published_at' => now(),
            'published_by_user_id' => User::factory(),
            'valid_from' => now()->toDateString(),
        ]);
    }
}
