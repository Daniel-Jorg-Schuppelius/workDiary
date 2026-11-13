<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AppWishVoteFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Database\Factories\Wishes;

use App\Models\Platform\{Organization, User};
use App\Models\Wishes\{AppWish, AppWishVote};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppWishVote>
 */
class AppWishVoteFactory extends Factory {
    protected $model = AppWishVote::class;

    public function definition(): array {
        return [
            'organization_id' => Organization::factory(),
            'app_wish_id' => AppWish::factory(),
            'user_id' => User::factory(),
        ];
    }
}
