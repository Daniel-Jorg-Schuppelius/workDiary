<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiCapabilitySettingFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Database\Factories\Ai;

use App\Models\Ai\AiCapabilitySetting;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiCapabilitySetting>
 */
class AiCapabilitySettingFactory extends Factory {
    protected $model = AiCapabilitySetting::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'organization_id' => Organization::factory(),
            'capability' => 'test.capability',
            'enabled' => false,
            'allow_user_choice' => false,
        ];
    }
}
