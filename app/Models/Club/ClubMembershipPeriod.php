<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubMembershipPeriod.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Club;

use App\Enums\Club\ClubMembershipKind;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Abschnitt des Mitgliedschaftsverlaufs (MVP-842): Art gilt von starts_on bis
 * ends_on (einschließlich); der offene Abschnitt hat kein Ende. Geschrieben
 * ausschließlich vom ClubMemberService — auditiert wird das Mitglied.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $club_member_id
 * @property ClubMembershipKind $kind
 * @property Carbon $starts_on
 * @property Carbon|null $ends_on
 * @property string|null $note
 */
class ClubMembershipPeriod extends Model {
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'club_member_id',
        'kind',
        'starts_on',
        'ends_on',
        'note',
    ];

    protected $casts = [
        'kind' => ClubMembershipKind::class,
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];

    /** @return BelongsTo<ClubMember, $this> */
    public function member(): BelongsTo {
        return $this->belongsTo(ClubMember::class, 'club_member_id');
    }
}
