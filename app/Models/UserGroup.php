<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UserGroup.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Scopes\OrganizationScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany};
use Illuminate\Support\{Carbon, Str};
use Spatie\Permission\Traits\HasRoles;

/**
 * Organisationsspezifische Benutzergruppe für die Rechteverwaltung im
 * neuen Bereich. Eine Gruppe bündelt Mitglieder (User) und vererbt ihnen
 * sowohl Rollen als auch direkte Permissions. Effektive Berechtigungen
 * eines Users sind die Vereinigung aus eigenen direkten Permissions, den
 * via eigenen Rollen erlangten Permissions und allen Permissions aller
 * Gruppen, in denen der User Mitglied ist.
 *
 * Slug muss innerhalb einer Organisation eindeutig sein und wird beim
 * Anlegen automatisch aus dem Namen abgeleitet.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $color
 * @property bool $is_system
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[ScopedBy([OrganizationScope::class])]
class UserGroup extends Model {
    use Auditable;
    use HasRoles;

    /**
     * Spatie's HasRoles ermittelt den Guard üblicherweise über den auth.providers-
     * Mapping zum Modell. Da UserGroup kein authentifizierbares Modell ist und
     * folglich keinen Provider hat, deklarieren wir den Guard explizit als 'web',
     * damit Rollen- und Permission-Zuweisungen funktionieren.
     */
    protected string $guard_name = 'web';

    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'description',
        'color',
        'is_system',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'is_system' => 'boolean',
    ];

    protected static function booted(): void {
        static::creating(function (UserGroup $group): void {
            if (! $group->slug) {
                $base = Str::slug($group->name) ?: 'gruppe';
                $slug = $base;
                $i = 2;
                while (
                    // TENANT-BYPASS: Slug-Eindeutigkeit innerhalb der Org wird
                    // explizit via where('organization_id', ...) erzwungen.
                    // Global Scope umgangen, weil booted() im Admin-Kontext
                    // ohne gebundene currentOrganization läuft.
                    static::query()
                    ->withoutGlobalScopes()
                    ->where('organization_id', $group->organization_id)
                    ->where('slug', $slug)
                    ->exists()
                ) {
                    $slug = $base . '-' . $i++;
                }
                $group->slug = $slug;
            }
        });
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function members(): BelongsToMany {
        return $this->belongsToMany(User::class, 'user_user_group')
            ->withPivot('joined_at')
            ->withTimestamps();
    }
}
