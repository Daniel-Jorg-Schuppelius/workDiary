<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DiaryEntryFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\Diary\Status as DiaryStatus;
use App\Models\DiaryEntry;
use App\Models\EntryType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiaryEntry>
 */
class DiaryEntryFactory extends Factory {
    protected $model = DiaryEntry::class;

    public function definition(): array {
        $start = fake()->dateTimeBetween('-1 month', '+1 month');
        $end = (clone $start)->modify('+1 hour');

        return [
            'user_id' => User::factory(),
            'content' => fake()->sentence(),
            'response' => null,
            'status' => 2,
            'start_at' => $start,
            'end_at' => $end,
            'is_archived' => false,
            'archived_at' => null,
        ];
    }

    /**
     * Variante f\u00fcr Service-Auftr\u00e4ge (Pflege/IT/HLK): setzt EntryType=service
     * und f\u00fcllt Auftragsfelder mit Defaults.
     */
    public function service(): self {
        return $this->state(function (array $attributes): array {
            $orgId = $attributes['organization_id'] ?? null;
            $type = EntryType::query()
                ->withoutGlobalScopes()
                ->where('slug', EntryType::SLUG_SERVICE)
                ->when($orgId, fn($q) => $q->where('organization_id', $orgId))
                ->first()
                ?? EntryType::query()
                ->withoutGlobalScopes()
                ->where('slug', EntryType::SLUG_SERVICE)
                ->whereNull('organization_id')
                ->first();

            return [
                'entry_type_id' => $type?->id,
                'title' => fake()->sentence(3),
                'priority' => 'normal',
                'scheduled_for' => $attributes['scheduled_for'] ?? fake()->date(),
                'service_minutes' => 60,
                'status' => DiaryStatus::Open->value,
            ];
        });
    }
}
