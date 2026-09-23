<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubHorseUse.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Club;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\{Event, User};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Pferdeeinsatz (MVP-854): Minuten je Pferd und Stunde, getrennt von der
 * Reiteranwesenheit — ein Pferdewechsel verdoppelt keine Trainingszeit.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $event_id
 * @property int $club_horse_id
 * @property int|null $club_member_id
 * @property int $minutes
 * @property string|null $note
 * @property int|null $recorded_by_user_id
 * @property Carbon $recorded_at
 */
class ClubHorseUse extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = ['organization_id', 'event_id', 'club_horse_id', 'club_member_id', 'minutes', 'note', 'recorded_by_user_id', 'recorded_at'];

    protected $casts = ['minutes' => 'integer', 'recorded_at' => 'datetime'];

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<ClubHorse, $this> */
    public function horse(): BelongsTo {
        return $this->belongsTo(ClubHorse::class, 'club_horse_id');
    }

    /** @return BelongsTo<ClubMember, $this> */
    public function member(): BelongsTo {
        return $this->belongsTo(ClubMember::class, 'club_member_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recordedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
