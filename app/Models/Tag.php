<?php

/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Tag.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;

class Tag extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<TagFactory> */
    use HasFactory;

    protected $fillable = ['name', 'slug', 'color', 'created_by', 'organization_id'];

    protected static function booted(): void
    {
        static::saving(function (Tag $tag): void {
            if (! $tag->slug) {
                $tag->slug = static::uniqueSlug($tag->name);
            }
        });
    }

    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'tag';
        $slug = $base;
        $i = 2;
        while (static::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()
        ) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public static function findOrCreateByName(string $name, ?int $userId = null): self
    {
        $name = trim($name);
        $slug = static::uniqueSlug($name);

        $existing = static::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
        if ($existing) {
            return $existing;
        }

        return static::create([
            'name' => $name,
            'slug' => $slug,
            'created_by' => $userId,
        ]);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return MorphToMany<DiaryEntry, $this> */
    public function diaryEntries(): MorphToMany
    {
        return $this->morphedByMany(DiaryEntry::class, 'taggable');
    }

    /** @return MorphToMany<OnCallShift, $this> */
    public function shifts(): MorphToMany
    {
        return $this->morphedByMany(OnCallShift::class, 'taggable');
    }

    /** @return MorphToMany<EmergencyAssignment, $this> */
    public function assignments(): MorphToMany
    {
        return $this->morphedByMany(EmergencyAssignment::class, 'taggable');
    }
}
