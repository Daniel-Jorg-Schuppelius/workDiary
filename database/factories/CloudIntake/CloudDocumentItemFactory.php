<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CloudDocumentItemFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\CloudIntake;

use App\Enums\CloudIntake\{CloudIntakeItemStatus, CloudIntakeProvider};
use App\Models\CloudIntake\{CloudDocumentConnection, CloudDocumentItem};
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CloudDocumentItem>
 */
class CloudDocumentItemFactory extends Factory {
    protected $model = CloudDocumentItem::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        $itemId = 'id:' . fake()->unique()->lexify('????????????');

        return [
            'organization_id' => Organization::factory(),
            'connection_id' => CloudDocumentConnection::factory(),
            'provider' => CloudIntakeProvider::Dropbox,
            'external_item_id' => $itemId,
            'revision' => (string) fake()->numberBetween(1, 20),
            'source_path' => 'Dokumente/' . fake()->word() . '.pdf',
            'sha256' => hash('sha256', $itemId),
            'size' => fake()->numberBetween(1_000, 5_000_000),
            'status' => CloudIntakeItemStatus::Imported,
        ];
    }
}
