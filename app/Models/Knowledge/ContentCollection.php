<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContentCollection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Knowledge;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Platform\User;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * Sammlung (MVP-809, Feature 155): Knoten im Sammlungsbaum der Organisation.
 * Sie verleiht keinen Zugriff — was darin liegt, sieht nur, wer den Inhalt
 * selbst sehen darf.
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $parent_id
 * @property string $title
 * @property string|null $description
 * @property string $visibility
 * @property int $position
 * @property int|null $created_by
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ContentCollection|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ContentCollection> $children
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ContentCollectionItem> $items
 */
class ContentCollection extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    public const VISIBILITY_ORGANIZATION = 'organization';

    public const VISIBILITY_PRIVATE = 'private';

    /** Tiefe des Baums — tiefer ordnet niemand mehr, man sucht nur noch. */
    public const MAX_DEPTH = 5;

    protected $table = 'collections';

    protected $fillable = [
        'organization_id',
        'parent_id',
        'title',
        'description',
        'visibility',
        'position',
        'created_by',
        'archived_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'archived_at' => 'datetime',
        'position' => 'integer',
    ];

    /** @return BelongsTo<ContentCollection, $this> */
    public function parent(): BelongsTo {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<ContentCollection, $this> */
    public function children(): HasMany {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return HasMany<ContentCollectionItem, $this> */
    public function items(): HasMany {
        return $this->hasMany(ContentCollectionItem::class, 'collection_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPrivate(): bool {
        return $this->visibility === self::VISIBILITY_PRIVATE;
    }

    public function isArchived(): bool {
        return $this->archived_at !== null;
    }

    /**
     * Private Sammlungen gehören ihrer Verfasserin — auch Admins sehen sie
     * nicht (wie private Notizen).
     *
     * @param  Builder<ContentCollection>  $query
     * @return Builder<ContentCollection>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder {
        return $query->where(static fn (Builder $q) => $q
            ->where('visibility', self::VISIBILITY_ORGANIZATION)
            ->orWhere('created_by', $user->id));
    }

    public function isVisibleTo(User $user): bool {
        return ! $this->isPrivate() || (int) $this->created_by === (int) $user->id;
    }

    /** Ebene im Baum, Wurzel = 1. */
    public function depth(): int {
        $depth = 1;
        $cursor = $this->parent;
        while ($cursor !== null && $depth <= self::MAX_DEPTH + 1) {
            $depth++;
            $cursor = $cursor->parent;
        }

        return $depth;
    }

    /** Liegt $other unterhalb dieser Sammlung (oder ist sie selbst)? */
    public function isAncestorOrSelf(ContentCollection $other): bool {
        $cursor = $other;
        $guard = 0;
        while ($cursor !== null && $guard++ < 1000) {
            if ((int) $cursor->id === (int) $this->id) {
                return true;
            }
            $cursor = $cursor->parent;
        }

        return false;
    }

    /** Höhe des Teilbaums unter dieser Sammlung (ohne Kinder = 0). */
    public function subtreeHeight(): int {
        $height = 0;
        foreach ($this->children as $child) {
            $height = max($height, 1 + $child->subtreeHeight());
        }

        return $height;
    }
}
