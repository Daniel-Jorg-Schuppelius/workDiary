<?php
/*
 * Created on   : Thu Jun 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Team.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, GeneratesUniqueSlug, HasSqid, Searchable};
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany};
use Illuminate\Support\Carbon;

/**
 * Operatives Arbeits-Team innerhalb einer Organisation. Bündelt Mitarbeiter
 * zu einer Einheit, der Aufträge (Projekte) zugewiesen und in der Aufgaben
 * verteilt werden. Bewusst getrennt von {@see UserGroup} (Rechte-Bündel):
 * ein Team organisiert Arbeit, keine Berechtigungen.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $color
 * @property int|null $lead_user_id
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Team extends Model {
    use Auditable;
    use BelongsToOrganization;
    use GeneratesUniqueSlug;

    /** @use HasFactory<TeamFactory> */
    use HasFactory;

    use HasSqid;
    use Searchable;

    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'description',
        'color',
        'lead_user_id',
        'archived_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'archived_at' => 'datetime',
    ];

    protected static function booted(): void {
        static::creating(function (Team $team): void {
            if (! $team->slug) {
                $team->slug = self::uniqueSlug($team->name, (int) $team->organization_id);
            }
        });
    }

    public static function uniqueSlug(string $name, int $organizationId, ?int $ignoreId = null): string {
        return self::resolveUniqueSlug($name, 'team', fn(string $slug): bool =>
            // TENANT-BYPASS: ohne Global Scope, weil $organizationId explizit übergeben wird;
            // der explizite where('organization_id', ...) erhält die Mandantengrenze.
            static::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('slug', $slug)
            ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
            ->exists());
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder {
        return $query->whereNull('archived_at');
    }

    /** @return BelongsTo<User, $this> */
    public function lead(): BelongsTo {
        return $this->belongsTo(User::class, 'lead_user_id');
    }

    /** @return BelongsToMany<User, $this> */
    public function members(): BelongsToMany {
        return $this->belongsToMany(User::class, 'team_user')
            ->withPivot(['is_lead', 'joined_at'])
            ->withTimestamps();
    }

    /** @return BelongsToMany<Project, $this> */
    public function projects(): BelongsToMany {
        return $this->belongsToMany(Project::class, 'project_team')->withTimestamps();
    }

    /** @return list<string> */
    protected function searchableColumns(): array {
        return ['name', 'description'];
    }
}
