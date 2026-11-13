<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServiceTemplateFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Database\Factories\ServicePlan;

use App\Enums\Period\PeriodKind;
use App\Models\Classification\EntryType;
use App\Models\Platform\Organization;
use App\Models\ServicePlan\ServiceTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Jährliche Pflicht-Leistungsvorlage mit erfundener Bezeichnung.
 *
 * @extends Factory<ServiceTemplate>
 */
class ServiceTemplateFactory extends Factory {
    protected $model = ServiceTemplate::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'organization_id' => Organization::factory(),
            'entry_type_id' => static fn (array $attributes) => EntryType::factory()->create(['organization_id' => $attributes['organization_id']])->id,
            'product_group_code' => null,
            'period_kind' => PeriodKind::Year,
            'is_mandatory' => true,
            'label' => 'Jahresleistung ' . fake()->unique()->numberBetween(1, 9999),
            'label_i18n' => null,
            'sort' => 0,
        ];
    }
}
