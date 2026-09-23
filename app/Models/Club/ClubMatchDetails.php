<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubMatchDetails.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Club;

use App\Enums\Club\ClubLineupStatus;
use App\Models\Calendar\Event;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Platform\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * Spieltag (MVP-852) als Erweiterung eines Vereinstermins: Mannschaft,
 * Gegner, Wettbewerb, Heim/Auswärts, Treffzeit, Aufstellungsfreigabe und
 * Ergebnis im Format des Sportartenprofils.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $event_id
 * @property int $club_group_id
 * @property int|null $club_squad_id
 * @property int|null $club_season_id
 * @property string $opponent_name
 * @property string|null $competition
 * @property bool $is_home
 * @property string|null $venue
 * @property Carbon|null $meet_at
 * @property ClubLineupStatus $lineup_status
 * @property Carbon|null $lineup_released_at
 * @property int|null $lineup_released_by_user_id
 * @property string|null $lineup_conflict_note
 * @property array<string, mixed>|null $result
 * @property string|null $result_summary
 * @property string|null $result_note
 * @property Carbon|null $result_recorded_at
 * @property int|null $result_recorded_by_user_id
 */
class ClubMatchDetails extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'event_id', 'club_group_id', 'club_squad_id', 'club_season_id', 'opponent_name', 'competition',
        'is_home', 'venue', 'meet_at', 'lineup_status', 'lineup_released_at', 'lineup_released_by_user_id', 'lineup_conflict_note',
        'result', 'result_summary', 'result_note', 'result_recorded_at', 'result_recorded_by_user_id',
    ];

    protected $casts = [
        'is_home' => 'boolean',
        'meet_at' => 'datetime',
        'lineup_status' => ClubLineupStatus::class,
        'lineup_released_at' => 'datetime',
        'result' => 'array',
        'result_recorded_at' => 'datetime',
    ];

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<ClubGroup, $this> */
    public function team(): BelongsTo {
        return $this->belongsTo(ClubGroup::class, 'club_group_id');
    }

    /** @return BelongsTo<ClubSquad, $this> */
    public function squad(): BelongsTo {
        return $this->belongsTo(ClubSquad::class, 'club_squad_id');
    }

    /** @return BelongsTo<ClubSeason, $this> */
    public function season(): BelongsTo {
        return $this->belongsTo(ClubSeason::class, 'club_season_id');
    }

    /** @return BelongsTo<User, $this> */
    public function lineupReleasedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'lineup_released_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function resultRecordedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'result_recorded_by_user_id');
    }

    /** @return HasMany<ClubLineupEntry, $this> */
    public function lineupEntries(): HasMany {
        return $this->hasMany(ClubLineupEntry::class, 'event_id', 'event_id');
    }

    /** @return HasMany<ClubMatchAvailability, $this> */
    public function availabilities(): HasMany {
        return $this->hasMany(ClubMatchAvailability::class, 'event_id', 'event_id');
    }

    public function isReleased(): bool {
        return $this->lineup_status === ClubLineupStatus::Released;
    }

    public function hasResult(): bool {
        return $this->result_recorded_at !== null;
    }
}
