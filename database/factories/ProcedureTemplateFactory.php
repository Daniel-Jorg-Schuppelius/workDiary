<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureTemplateFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Models\Organization;
use App\Models\ProcedureTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProcedureTemplate>
 */
class ProcedureTemplateFactory extends Factory {
    protected $model = ProcedureTemplate::class;

    public function definition(): array {
        return [
            'organization_id' => Organization::factory(),
            'code' => strtoupper(fake()->unique()->bothify('PROC-####')),
            'name' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'domain' => fake()->randomElement(['it', 'electric', 'general']),
            'active' => true,
        ];
    }

    public function inactive(): self {
        return $this->state(fn() => ['active' => false]);
    }
}
