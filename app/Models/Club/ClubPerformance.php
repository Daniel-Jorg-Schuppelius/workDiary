<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubPerformance.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Club;

use App\Models\Calendar\Event;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Platform\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Manuell erfasste Leistung (MVP-855): Wert mit Einheit und Vergleichsrichtung
 * (Schnappschuss aus dem Profil), Platzierung, Bestätigung — Bestleistungen
 * entstehen nur aus bestätigten Werten.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $club_member_id
 * @property int $club_sport_profile_id
 * @property int|null $event_id
 * @property string $discipline_code
 * @property Carbon $performed_on
 * @property numeric-string $value
 * @property string|null $unit
 * @property bool $lower_is_better
 * @property int|null $placement
 * @property string|null $note
 * @property Carbon|null $confirmed_at
 * @property int|null $confirmed_by_user_id
 * @property int|null $recorded_by_user_id
 */
class ClubPerformance extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = ['organization_id', 'club_member_id', 'club_sport_profile_id', 'event_id', 'discipline_code', 'performed_on', 'value', 'unit', 'lower_is_better', 'placement', 'note', 'confirmed_at', 'confirmed_by_user_id', 'recorded_by_user_id'];

    protected $casts = [
        'performed_on' => 'date',
        'value' => 'decimal:3',
        'lower_is_better' => 'boolean',
        'placement' => 'integer',
        'confirmed_at' => 'datetime',
    ];

    /** @return BelongsTo<ClubMember, $this> */
    public function member(): BelongsTo {
        return $this->belongsTo(ClubMember::class, 'club_member_id');
    }

    /** @return BelongsTo<ClubSportProfile, $this> */
    public function profile(): BelongsTo {
        return $this->belongsTo(ClubSportProfile::class, 'club_sport_profile_id');
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<User, $this> */
    public function confirmedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'confirmed_by_user_id');
    }

    public function isConfirmed(): bool {
        return $this->confirmed_at !== null;
    }

    /** Ist dieser Wert besser als der andere (nach Vergleichsrichtung)? */
    public function beats(self $other): bool {
        return $this->lower_is_better ? (float) $this->value < (float) $other->value : (float) $this->value > (float) $other->value;
    }

    public function formattedValue(): string {
        $value = \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($this->value, 3, false, null, true);

        return $value . ($this->unit !== null ? ' ' . $this->unit : '');
    }
}
