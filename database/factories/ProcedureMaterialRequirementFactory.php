<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureMaterialRequirementFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\Manufacturing\QuantityKind;
use App\Models\{Article, ProcedureMaterialRequirement, ProcedureTemplateVersion};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProcedureMaterialRequirement>
 */
class ProcedureMaterialRequirementFactory extends Factory {
    protected $model = ProcedureMaterialRequirement::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'procedure_template_version_id' => ProcedureTemplateVersion::factory(),
            'position_code' => fake()->unique()->lexify('pos_????'),
            'article_id' => Article::factory(),
            'quantity_kind' => QuantityKind::PerUnit->value,
            'quantity' => '1',
            'unit' => 'Stk',
            'rounding' => 'none',
            'is_tool' => false,
            'position' => 0,
            'active' => true,
        ];
    }

    public function perUnit(string $qty): self {
        return $this->state(fn () => ['quantity_kind' => QuantityKind::PerUnit->value, 'quantity' => $qty]);
    }

    public function fixed(string $qty): self {
        return $this->state(fn () => ['quantity_kind' => QuantityKind::Fixed->value, 'quantity' => $qty]);
    }

    public function ratio(string $part, string $unit = 'kg'): self {
        return $this->state(fn () => ['quantity_kind' => QuantityKind::Ratio->value, 'ratio_part' => $part, 'unit' => $unit]);
    }

    public function withWaste(string $percent): self {
        return $this->state(fn () => ['waste_surcharge' => $percent]);
    }
}
