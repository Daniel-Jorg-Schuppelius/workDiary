<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubMemberProof.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Club;

use App\Enums\Club\ClubProofKind;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Nachweis eines Mitglieds (MVP-846): bestätigter Pflichtlehrgang oder externer
 * Trainingsnachweis mit Herkunft, Zeitraum und bestätigender Person — keine
 * ungeprüfte Anfangssumme.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $club_member_id
 * @property ClubProofKind $kind
 * @property string $label
 * @property string|null $discipline
 * @property int|null $minutes
 * @property int|null $sessions
 * @property Carbon $obtained_on
 * @property Carbon|null $valid_until
 * @property string|null $origin
 * @property int|null $confirmed_by_user_id
 * @property string|null $note
 */
class ClubMemberProof extends Model {
    use Auditable;

    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = ['organization_id', 'club_member_id', 'kind', 'label', 'discipline', 'minutes', 'sessions', 'obtained_on', 'valid_until', 'origin', 'confirmed_by_user_id', 'note'];

    protected $casts = [
        'kind' => ClubProofKind::class,
        'minutes' => 'integer',
        'sessions' => 'integer',
        'obtained_on' => 'date',
        'valid_until' => 'date',
    ];

    /** @return BelongsTo<ClubMember, $this> */
    public function member(): BelongsTo {
        return $this->belongsTo(ClubMember::class, 'club_member_id');
    }

    /** @return BelongsTo<User, $this> */
    public function confirmedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'confirmed_by_user_id');
    }
}
