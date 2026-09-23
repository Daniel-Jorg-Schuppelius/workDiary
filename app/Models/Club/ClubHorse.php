<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubHorse.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Club;

use App\Enums\Club\ClubHorseKind;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany};

/**
 * Pferdeprofil (Feature 159, MVP-854): schlank, auf einer Ressource der Art
 * „Pferd“ — Belegung, Ruhepuffer, Sperrzeiten und Eignungsfreigaben laufen
 * über die Ressource. Kein Mitglied, kein Benutzer, kein Lagerartikel.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $club_resource_id
 * @property string $name
 * @property ClubHorseKind $kind
 * @property int|null $owner_member_id
 * @property string|null $contact
 * @property int|null $max_uses_per_day
 * @property string|null $suitable_for
 * @property bool $is_active
 * @property string|null $notes
 */
class ClubHorse extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = ['organization_id', 'club_resource_id', 'name', 'kind', 'owner_member_id', 'contact', 'max_uses_per_day', 'suitable_for', 'is_active', 'notes'];

    protected $casts = ['kind' => ClubHorseKind::class, 'max_uses_per_day' => 'integer', 'is_active' => 'boolean'];

    /** @return BelongsTo<ClubResource, $this> */
    public function resource(): BelongsTo {
        return $this->belongsTo(ClubResource::class, 'club_resource_id');
    }

    /** @return BelongsTo<ClubMember, $this> */
    public function owner(): BelongsTo {
        return $this->belongsTo(ClubMember::class, 'owner_member_id');
    }

    /** @return BelongsToMany<ClubGroup, $this> */
    public function groups(): BelongsToMany {
        return $this->belongsToMany(ClubGroup::class, 'club_horse_groups')->withPivot('organization_id')->withTimestamps();
    }

    /** @return HasMany<ClubHorseAssignment, $this> */
    public function assignments(): HasMany {
        return $this->hasMany(ClubHorseAssignment::class);
    }

    /** @return HasMany<ClubHorseUse, $this> */
    public function uses(): HasMany {
        return $this->hasMany(ClubHorseUse::class);
    }

    public function isPrivate(): bool {
        return $this->kind === ClubHorseKind::Private;
    }
}
