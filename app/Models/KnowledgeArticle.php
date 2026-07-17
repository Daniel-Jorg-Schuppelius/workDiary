<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgeArticle.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Knowledge\{ArticleStatus, ArticleVisibility};
use App\Models\Concerns\{Auditable, BelongsToOrganization, GeneratesUniqueSlug, HasAttachments, HasSqid, HasTags, Searchable};
use Database\Factories\KnowledgeArticleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\{Carbon, Str};

/**
 * Wissensartikel (Feature 011): bekanntes Problem + Lösungsschritte aus
 * dem Tagesgeschäft, verknüpfbar mit Aufträgen/Assets/Kunden/Protokollen
 * (Problemhistorie). Screenshots laufen über HasAttachments, Tags über
 * die bestehende polymorphe Tag-Mechanik (HasTags).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $title
 * @property string $slug
 * @property string $problem
 * @property string $solution
 * @property string|null $category
 * @property ArticleStatus $status
 * @property ArticleVisibility $visibility
 * @property int $created_by_user_id
 * @property Carbon|null $published_at
 * @property int $helpful_count
 * @property int $not_helpful_count
 */
class KnowledgeArticle extends Model {
    use Auditable;

    use BelongsToOrganization;
    use GeneratesUniqueSlug;
    use HasAttachments;
    /** @use HasFactory<KnowledgeArticleFactory> */
    use HasFactory;
    use HasSqid;
    use HasTags;
    use Searchable;

    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'title',
        'slug',
        'problem',
        'solution',
        'category',
        'status',
        'visibility',
        'created_by_user_id',
        'published_at',
        'helpful_count',
        'not_helpful_count',
    ];

    protected $casts = [
        'status' => ArticleStatus::class,
        'visibility' => ArticleVisibility::class,
        'published_at' => 'datetime',
        'helpful_count' => 'integer',
        'not_helpful_count' => 'integer',
    ];

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return HasMany<KnowledgeArticleLink, $this> */
    public function links(): HasMany {
        return $this->hasMany(KnowledgeArticleLink::class);
    }

    /** @return HasMany<KnowledgeArticleFeedback, $this> */
    public function feedback(): HasMany {
        return $this->hasMany(KnowledgeArticleFeedback::class);
    }

    /**
     * Veröffentlichte Artikel (Suche, Vorschläge, Standard-Sicht).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePublished(Builder $query): Builder {
        return $query->where('status', ArticleStatus::Published->value);
    }

    /**
     * Einfache LIKE-Suche via Searchable-Trait (bewusst keine
     * Volltext-Engine, siehe Feature 011 Out-of-Scope).
     *
     * @return list<string>
     */
    protected function searchableColumns(): array {
        return ['title', 'problem', 'solution'];
    }

    /**
     * Eindeutiger Slug je Organisation (Unique-Index knowledge_org_slug_uq).
     * Läuft innerhalb des BelongsToOrganization-Scopes — der Vergleich
     * trifft also genau die Artikel der aktuellen Organisation.
     */
    public static function uniqueSlug(string $title, ?int $ignoreId = null): string {
        return self::resolveUniqueSlug(Str::limit($title, 180, ''), 'artikel', fn(string $slug): bool => static::query()
            ->withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn($q) => $q->where('id', '!=', $ignoreId))
            ->exists());
    }
}
