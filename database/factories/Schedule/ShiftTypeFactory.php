<?php
/*
 * Created on   : Mon May 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShiftTypeFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Schedule;

use App\Models\Schedule\ShiftType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShiftType>
 */
class ShiftTypeFactory extends Factory {
    /**
     * Name und Regelzeiten gehören zusammen: unabhängig gewürfelte Zeiten
     * ergaben in gut jedem dritten Fall 14–24-h-Dienste und damit zufällige
     * Verstöße gegen die Tageshöchstarbeitszeit (MVP-1713). Lange Dienste
     * setzt der Test ausdrücklich.
     *
     * @var list<array{0: string, 1: ?string, 2: ?string}>
     */
    private const array PRESETS = [
        ['Frühdienst', '06:00', '14:00'],
        ['Spätdienst', '14:00', '22:00'],
        ['Nachtdienst', '22:00', '06:00'],
        ['Wochenenddienst', '08:00', '16:00'],
        ['Rufbereitschaft', null, null],
    ];

    protected $model = ShiftType::class;

    public function definition(): array {
        [$name, $start, $end] = fake()->randomElement(self::PRESETS);
        $colors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'];

        return [
            'name' => $name,
            'abbreviation' => mb_strtoupper(mb_substr($name, 0, 2)),
            'color' => fake()->randomElement($colors),
            'default_start_time' => $start,
            'default_end_time' => $end,
            'is_active' => true,
        ];
    }

    public function inactive(): static {
        return $this->state(['is_active' => false]);
    }
}
