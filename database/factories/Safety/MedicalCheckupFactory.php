<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MedicalCheckupFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Safety;

use App\Enums\Safety\MedicalCheckupKind;
use App\Models\Safety\MedicalCheckup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MedicalCheckup>
 */
class MedicalCheckupFactory extends Factory {
    protected $model = MedicalCheckup::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'user_id' => User::factory(),
            'kind' => MedicalCheckupKind::Offered->value,
            'occasion' => fake()->randomElement(['Bildschirmarbeit', 'Lärm', 'Fahr-/Steuertätigkeit']),
            'performed_on' => now()->subMonths(6)->toDateString(),
            'next_due_on' => now()->addMonths(30)->toDateString(),
            'certificate_on_file' => true,
            'created_by_user_id' => null,
        ];
    }
}
