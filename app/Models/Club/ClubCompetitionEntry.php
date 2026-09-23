<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubCompetitionEntry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Club;

use App\Casts\MoneyCast;
use App\Enums\Club\ClubEntryStatus;
use App\Models\Calendar\Event;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Platform\User;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Wettkampfmeldung (MVP-855): Mitglied × Disziplin je Wettkampf; ohne gültiges
 * Startrecht „zur Klärung“, nie still abgelehnt. Die Meldegebühr wird als
 * eigene Beitragsposition abgerechnet.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $event_id
 * @property int $club_member_id
 * @property string $discipline_code
 * @property ClubEntryStatus $status
 * @property string|null $review_reason
 * @property Money|null $fee_amount
 * @property CurrencyCode $currency
 * @property int|null $registered_by_user_id
 * @property Carbon $registered_at
 */
class ClubCompetitionEntry extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = ['organization_id', 'event_id', 'club_member_id', 'discipline_code', 'status', 'review_reason', 'fee_amount', 'currency', 'registered_by_user_id', 'registered_at'];

    protected $casts = [
        'status' => ClubEntryStatus::class,
        'fee_amount' => MoneyCast::class . ':currency',
        'currency' => CurrencyCode::class,
        'registered_at' => 'datetime',
    ];

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<ClubMember, $this> */
    public function member(): BelongsTo {
        return $this->belongsTo(ClubMember::class, 'club_member_id');
    }

    /** @return BelongsTo<User, $this> */
    public function registeredBy(): BelongsTo {
        return $this->belongsTo(User::class, 'registered_by_user_id');
    }
}
