<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;

class Tag extends Model {
    use HasFactory;

    protected $fillable = ['name', 'slug', 'color', 'created_by'];

    protected static function booted(): void {
        static::saving(function (Tag $tag): void {
            if (! $tag->slug) {
                $tag->slug = static::uniqueSlug($tag->name);
            }
        });
    }

    public static function uniqueSlug(string $name, ?int $ignoreId = null): string {
        $base = Str::slug($name) ?: 'tag';
        $slug = $base;
        $i = 2;
        while (static::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
            ->exists()
        ) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    public static function findOrCreateByName(string $name, ?int $userId = null): self {
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

    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function diaryEntries(): MorphToMany {
        return $this->morphedByMany(DiaryEntry::class, 'taggable');
    }

    public function shifts(): MorphToMany {
        return $this->morphedByMany(OnCallShift::class, 'taggable');
    }

    public function assignments(): MorphToMany {
        return $this->morphedByMany(EmergencyAssignment::class, 'taggable');
    }
}
