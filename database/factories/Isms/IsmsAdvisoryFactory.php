<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsAdvisoryFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Isms;

use App\Enums\Isms\AdvisoryFormat;
use App\Models\Isms\IsmsAdvisory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IsmsAdvisory>
 */
class IsmsAdvisoryFactory extends Factory {
    protected $model = IsmsAdvisory::class;

    public function definition(): array {
        $hash = hash('sha256', fake()->unique()->uuid());

        return [
            'title' => fake()->sentence(4),
            'format' => AdvisoryFormat::Csaf->value,
            'document_id_ref' => 'CSAF-' . fake()->unique()->numberBetween(1000, 9999),
            'file_path' => 'isms/advisories/' . $hash . '.json',
            'file_hash' => $hash,
            'imported_by_user_id' => null,
            'vuln_count' => fake()->numberBetween(0, 5),
        ];
    }

    public function vex(): self {
        return $this->state(fn() => ['format' => AdvisoryFormat::Vex->value]);
    }
}
