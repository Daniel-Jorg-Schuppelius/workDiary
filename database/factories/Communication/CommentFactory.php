<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CommentFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Communication;

use App\Models\Communication\Comment;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Support\MorphMap;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory {
    protected $model = Comment::class;

    public function definition(): array {
        return [
            'commentable_type' => MorphMap::alias(DiaryEntry::class),
            'commentable_id' => DiaryEntry::factory(),
            'user_id' => User::factory(),
            'body' => fake()->sentence(),
        ];
    }
}
