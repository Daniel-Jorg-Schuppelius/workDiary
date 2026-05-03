<?php

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\DiaryEntry;
use App\Models\User;
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
