<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AppWishFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Database\Factories\Wishes;

use App\Enums\Wishes\{AppWishKind, AppWishStatus};
use App\Models\Platform\Organization;
use App\Models\Wishes\AppWish;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppWish>
 */
class AppWishFactory extends Factory {
    protected $model = AppWish::class;

    public function definition(): array {
        return [
            'organization_id' => Organization::factory(),
            'kind' => AppWishKind::Wish,
            'body' => 'Die Liste sollte sich nach Fälligkeit sortieren lassen.',
            'route_name' => 'work-queue.index',
            'status' => AppWishStatus::New,
            'app_version' => '1.0.0',
        ];
    }

    /** Weitergegeben an den Plattformbetrieb (MVP-1659). */
    public function forwarded(?string $body = null): static {
        return $this->state(fn (array $attributes): array => [
            'forwarded_at' => now(),
            'forwarded_body' => $body ?? (string) ($attributes['body'] ?? ''),
        ]);
    }
}
