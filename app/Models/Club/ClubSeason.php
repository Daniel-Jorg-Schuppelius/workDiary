<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubSeason.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Club;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Saison (MVP-852): Zeitraum für Saisonkader und Spieltage; Vorsaisons bleiben erhalten.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 */
class ClubSeason extends Model {
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = ['organization_id', 'name', 'starts_on', 'ends_on'];

    protected $casts = ['starts_on' => 'date', 'ends_on' => 'date'];

    /** @return HasMany<ClubSquad, $this> */
    public function squads(): HasMany {
        return $this->hasMany(ClubSquad::class);
    }

    public function contains(CarbonInterface $date): bool {
        return ! $date->lt($this->starts_on->startOfDay()) && ! $date->gt($this->ends_on->endOfDay());
    }

    /** @param  Builder<ClubSeason>  $query
     * @return Builder<ClubSeason> */
    public function scopeContaining(Builder $query, CarbonInterface $date): Builder {
        $day = \App\Support\Query\DateRange::day($date);

        return $query->where('starts_on', '<=', $day)->where('ends_on', '>=', $day);
    }
}
