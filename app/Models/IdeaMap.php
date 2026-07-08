<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IdeaMap.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Ideas\IdeaMapVisibility;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid, HasTags};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasOne, MorphMany};
use Illuminate\Support\Carbon;

/**
 * Ideenlandkarte (Feature 054, MVP-104/105). Datenschutz-Grundsatz: `private`
 * ist Default; sichtbar ist eine Karte ausschließlich für den Eigentümer und
 * ausdrücklich freigegebene Personen/Teams ({@see scopeVisibleTo()} +
 * {@see \App\Policies\IdeaMapPolicy}). Org-Admins erhalten über
 * `viewMeta`/`manageLifecycle` nur Metadaten — nie Knoteninhalt.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $created_by
 * @property int $owner_user_id
 * @property string $title
 * @property string|null $description
 * @property IdeaMapVisibility $visibility
 * @property int|null $customer_id
 * @property int|null $project_id
 * @property int|null $diary_entry_id
 * @property Carbon|null $archived_at
 */
class IdeaMap extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;
    use HasSqid;
    use HasTags;
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'created_by',
        'owner_user_id',
        'title',
        'description',
        'visibility',
        'lock_version',
        'customer_id',
        'project_id',
        'diary_entry_id',
        'archived_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'visibility' => IdeaMapVisibility::class,
        'lock_version' => 'integer',
        'archived_at' => 'datetime',
    ];

    /**
     * Pflicht-Einstieg für jede Listen-/Such-/Export-Query: Eigentümer ∪
     * direkte Personen-Freigabe ∪ Team-Freigabe (aufgelöst beim Zugriff über
     * `team_user` — Verlassen des Teams entzieht sofort). NIE durch einen
     * reinen Org-Scope ersetzen.
     *
     * @param  Builder<IdeaMap>  $query
     * @return Builder<IdeaMap>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder {
        return $query->where(function (Builder $q) use ($user): void {
            $q->where('owner_user_id', $user->id)
                ->orWhereHas('shares', function (Builder $share) use ($user): void {
                    $share->where(function (Builder $s) use ($user): void {
                        $s->where('user_id', $user->id)
                            ->orWhereIn('team_id', $user->teams()->select('teams.id'));
                    });
                });
        });
    }

    /** Ist der Nutzer Eigentümer der Karte? */
    public function isOwnedBy(User $user): bool {
        return (int) $this->owner_user_id === (int) $user->id;
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<IdeaNode, $this> */
    public function nodes(): HasMany {
        return $this->hasMany(IdeaNode::class);
    }

    /** @return HasOne<IdeaNode, $this> */
    public function rootNode(): HasOne {
        return $this->hasOne(IdeaNode::class)->where('is_root', true);
    }

    /** @return HasMany<IdeaMapShare, $this> */
    public function shares(): HasMany {
        return $this->hasMany(IdeaMapShare::class);
    }

    /** @return HasMany<IdeaNodeLink, $this> Querverbindungen (MVP-137). */
    public function links(): HasMany {
        return $this->hasMany(IdeaNodeLink::class);
    }

    /** @return HasMany<IdeaNodeSummary, $this> Boundaries/Zusammenfassungen (MVP-137). */
    public function summaries(): HasMany {
        return $this->hasMany(IdeaNodeSummary::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<DiaryEntry, $this> */
    public function diaryEntry(): BelongsTo {
        return $this->belongsTo(DiaryEntry::class);
    }

    /** @return MorphMany<Comment, $this> */
    public function comments(): MorphMany {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function isArchived(): bool {
        return $this->archived_at !== null;
    }
}
