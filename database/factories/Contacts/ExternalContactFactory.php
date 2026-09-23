<?php
/*
 * Created on   : Mon Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalContactFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\ExternalParticipant\ExternalParty;
use App\Models\ExternalContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExternalContact>
 */
class ExternalContactFactory extends Factory {
    protected $model = ExternalContact::class;

    public function definition(): array {
        return [
            'organization_id' => null,
            'name' => fake()->company(),
            'email' => fake()->safeEmail(),
            'role' => 'Prüfer',
            'party' => ExternalParty::Inspector->value,
            'notes' => null,
        ];
    }
}
