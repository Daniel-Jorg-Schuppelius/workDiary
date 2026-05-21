<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EventCategoryFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Models\EventCategory;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EventCategory>
 */
class EventCategoryFactory extends Factory {
    protected $model = EventCategory::class;

    public function definition(): array {
        $name = fake()->randomElement([
            'Pflichtschulung',
            'Sicherheitsunterweisung',
            'Datenschutz',
            'Brandschutz',
            'Erste Hilfe',
            'Fachfortbildung',
            'Teammeeting',
        ]) . ' ' . fake()->unique()->numberBetween(1, 9999);

        return [
            'organization_id' => Organization::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'color' => fake()->hexColor(),
            'description' => fake()->optional()->sentence(),
            'requires_certificate' => false,
            'certificate_valid_months' => null,
            'reminder_offsets' => [10080, 1440, 60],
            'is_active' => true,
        ];
    }

    public function withCertificate(int $validMonths = 12): self {
        return $this->state(fn(): array => [
            'requires_certificate' => true,
            'certificate_valid_months' => $validMonths,
        ]);
    }
}
