<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubSquad.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Club;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Saisonkader einer Mannschaft (MVP-852): Mitglieder und Gastspieler mit Gültigkeit je Saison.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $club_group_id
 * @property int $club_season_id
 * @property string|null $name
 * @property string|null $notes
 */
class ClubSquad extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = ['organization_id', 'club_group_id', 'club_season_id', 'name', 'notes'];

    /** @return BelongsTo<ClubGroup, $this> */
    public function group(): BelongsTo {
        return $this->belongsTo(ClubGroup::class, 'club_group_id');
    }

    /** @return BelongsTo<ClubSeason, $this> */
    public function season(): BelongsTo {
        return $this->belongsTo(ClubSeason::class, 'club_season_id');
    }

    /** @return HasMany<ClubSquadMember, $this> */
    public function members(): HasMany {
        return $this->hasMany(ClubSquadMember::class);
    }

    /** @return HasMany<ClubSquadMember, $this> */
    public function membersOn(CarbonInterface $date): HasMany {
        $day = \App\Support\Query\DateRange::day($date);

        return $this->members()
            ->where('valid_from', '<=', $day)
            ->where(fn($query) => $query->whereNull('valid_to')->orWhere('valid_to', '>=', $day));
    }
}
