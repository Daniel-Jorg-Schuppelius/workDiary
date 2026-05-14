<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\DiaryEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    protected $model = Comment::class;

    public function definition(): array
    {
        return [
            'diary_entry_id' => DiaryEntry::factory(),
            'user_id' => User::factory(),
            'body' => fake()->sentence(),
        ];
    }
}
