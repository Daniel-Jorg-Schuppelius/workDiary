<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LeadIntakeFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Enums\Fields\FieldType;
use App\Enums\Sales\{LeadSource, LeadStatus};
use App\Models\Platform\Organization;
use App\Models\Sales\{Lead, LeadIntake};
use App\Services\Fields\{FieldDefinition, FieldDocument, FieldSchema, FieldValues};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Aufnahmeakte ohne Prüfpaket, nur mit dem Grundteil; Lead erfunden.
 *
 * @extends Factory<LeadIntake>
 */
class LeadIntakeFactory extends Factory {
    protected $model = LeadIntake::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'organization_id' => Organization::factory(),
            'lead_id' => static fn (array $attributes) => Lead::query()->create([
                'organization_id' => $attributes['organization_id'],
                'company' => 'Beispiel GmbH (Test)',
                'source' => LeadSource::Web,
                'status' => LeadStatus::Qualified,
            ])->id,
            'stage' => 'inquiry',
            'packages' => [],
            'form' => new FieldDocument(new FieldSchema([
                new FieldDefinition('intake', FieldType::Section, 'Anfrage und Gespräch'),
                new FieldDefinition('request', FieldType::Textarea, 'Anliegen', required: true),
            ]), new FieldValues),
            'version' => 1,
            'revision' => 1,
        ];
    }
}
