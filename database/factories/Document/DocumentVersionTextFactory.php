<?php
/*
 * Created on   : Sat Sep 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentVersionTextFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Database\Factories\Document;

use App\Models\Document\Document\DocumentVersion;
use App\Models\Document\Document\DocumentVersionText;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DocumentVersionText> */
class DocumentVersionTextFactory extends Factory {
    protected $model = DocumentVersionText::class;

    /** @return array<string, mixed> */
    public function definition(): array {
        return [
            'document_version_id' => DocumentVersion::factory(),
            'text' => $this->faker->paragraph(),
            'extracted_at' => now(),
            'failure_reason' => null,
        ];
    }
}
