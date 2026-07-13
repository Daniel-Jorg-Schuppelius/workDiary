<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HasTags.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Concerns;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Auth;

trait HasTags {
    /** @return MorphToMany<Tag, $this> */
    public function tags(): MorphToMany {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    /**
     * Sync tags from a mix of existing tag IDs and free-form names.
     *
     * @param  array<int|string>  $tagIds  Existing tag IDs.
     * @param  array<string>  $newNames  Names of tags to create on the fly.
     */
    public function syncTagsFromInput(array $tagIds = [], array $newNames = []): void {
        $ids = collect($tagIds)
            ->filter(fn($v) => is_numeric($v))
            ->map(fn($v) => (int) $v)
            ->all();

        foreach ($newNames as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            $tag = Tag::findOrCreateByName($name, Auth::id() !== null ? (int) Auth::id() : null);
            $ids[] = $tag->id;
        }

        $this->tags()->sync(array_values(array_unique($ids)));
    }
}
