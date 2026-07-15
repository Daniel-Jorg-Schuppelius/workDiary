<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CloudDocumentRouteFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\CloudIntake;

use App\Enums\CloudIntake\CloudIntakeRouteTarget;
use App\Models\CloudIntake\{CloudDocumentConnection, CloudDocumentRoute};
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CloudDocumentRoute>
 */
class CloudDocumentRouteFactory extends Factory {
    protected $model = CloudDocumentRoute::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'organization_id' => Organization::factory(),
            'connection_id' => CloudDocumentConnection::factory(),
            'priority' => 100,
            'path_pattern' => 'Dokumente/**',
            'target' => CloudIntakeRouteTarget::Document,
            'document_type' => 'other',
            'auto_version' => false,
            'active' => true,
        ];
    }
}
