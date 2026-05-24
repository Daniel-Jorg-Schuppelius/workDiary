<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CommentFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Models\{Comment, DiaryEntry, User};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory {
    protected $model = Comment::class;

    public function definition(): array {
        return [
            'commentable_type' => DiaryEntry::class,
            'commentable_id' => DiaryEntry::factory(),
            'user_id' => User::factory(),
            'body' => fake()->sentence(),
        ];
    }
}
