<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubStartRight.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Club;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Startrecht/Startpass (MVP-855): dokumentierte Prüfung mit Gültigkeit durch
 * die Leitung — kein Verbandsabgleich. Ohne Profil gilt es für alle Sportarten.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $club_member_id
 * @property int|null $club_sport_profile_id
 * @property string|null $reference
 * @property Carbon $valid_from
 * @property Carbon|null $valid_to
 * @property string|null $note
 * @property int|null $granted_by_user_id
 */
class ClubStartRight extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = ['organization_id', 'club_member_id', 'club_sport_profile_id', 'reference', 'valid_from', 'valid_to', 'note', 'granted_by_user_id'];

    protected $casts = ['valid_from' => 'date', 'valid_to' => 'date'];

    /** @return BelongsTo<ClubMember, $this> */
    public function member(): BelongsTo {
        return $this->belongsTo(ClubMember::class, 'club_member_id');
    }

    /** @return BelongsTo<ClubSportProfile, $this> */
    public function profile(): BelongsTo {
        return $this->belongsTo(ClubSportProfile::class, 'club_sport_profile_id');
    }

    /** @return BelongsTo<User, $this> */
    public function grantedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'granted_by_user_id');
    }

    public function isValidOn(CarbonInterface $day): bool {
        return ! $day->lt($this->valid_from->startOfDay()) && ($this->valid_to === null || ! $day->gt($this->valid_to->endOfDay()));
    }
}
