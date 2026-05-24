<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AttachmentFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Models\{Attachment, DiaryEntry, User};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory {
    protected $model = Attachment::class;

    public function definition(): array {
        $entry = DiaryEntry::factory()->create();

        return [
            'attachable_type' => DiaryEntry::class,
            'attachable_id' => $entry->id,
            'user_id' => User::factory(),
            'disk' => 'local',
            'path' => 'attachments/test/' . fake()->uuid() . '.txt',
            'original_name' => 'test.txt',
            'mime' => 'text/plain',
            'size' => fake()->numberBetween(100, 10000),
        ];
    }
}
