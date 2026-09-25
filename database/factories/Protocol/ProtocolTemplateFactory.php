<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolTemplateFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Protocol;

use App\Enums\Protocol\{ProtocolItemType, ProtocolType};
use App\Models\Platform\Organization;
use App\Models\Protocol\ProtocolTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProtocolTemplate>
 */
class ProtocolTemplateFactory extends Factory {
    protected $model = ProtocolTemplate::class;

    public function definition(): array {
        return [
            'organization_id' => Organization::factory(),
            'name' => 'Wartung ' . fake()->word(),
            'kind' => ProtocolType::Maintenance->value,
            'items' => [
                ['label' => 'Sichtprüfung', 'item_type' => ProtocolItemType::Boolean->value, 'required' => true],
                ['label' => 'Bemerkung', 'item_type' => ProtocolItemType::Text->value],
            ],
            'version' => 1,
            'is_active' => true,
        ];
    }
}
