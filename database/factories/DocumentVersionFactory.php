<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentVersionFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Models\{Document, DocumentVersion, User};
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DocumentVersion>
 */
class DocumentVersionFactory extends Factory {
    protected $model = DocumentVersion::class;

    public function definition(): array {
        return [
            'document_id' => Document::factory(),
            'version_no' => 1,
            'disk' => 'local',
            'path' => 'documents/' . now()->format('Y/m') . '/' . Str::uuid()->toString() . '.pdf',
            'original_name' => fake()->slug(2) . '.pdf',
            'mime' => 'application/pdf',
            'size' => fake()->numberBetween(1024, 1024 * 1024),
            'uploaded_by_user_id' => User::factory(),
            'note' => null,
        ];
    }
}
