<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubCompetitionDetails.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Club;

use App\Casts\MoneyCast;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Event;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Wettkampf (MVP-855) als Erweiterung eines Vereinstermins: Sportartenprofil,
 * angebotene Disziplinen, Ort/Ausrichter, optionale Meldegebühr, Startrechtspflicht.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $event_id
 * @property int $club_sport_profile_id
 * @property list<string> $disciplines
 * @property string|null $venue
 * @property string|null $organizer
 * @property Money|null $entry_fee
 * @property CurrencyCode $currency
 * @property bool $requires_start_right
 * @property string|null $notes
 */
class ClubCompetitionDetails extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = ['organization_id', 'event_id', 'club_sport_profile_id', 'disciplines', 'venue', 'organizer', 'entry_fee', 'currency', 'requires_start_right', 'notes'];

    protected $casts = [
        'disciplines' => 'array',
        'entry_fee' => MoneyCast::class . ':currency',
        'currency' => CurrencyCode::class,
        'requires_start_right' => 'boolean',
    ];

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<ClubSportProfile, $this> */
    public function profile(): BelongsTo {
        return $this->belongsTo(ClubSportProfile::class, 'club_sport_profile_id');
    }

    /** @return HasMany<ClubCompetitionEntry, $this> */
    public function entries(): HasMany {
        return $this->hasMany(ClubCompetitionEntry::class, 'event_id', 'event_id');
    }

    /**
     * Disziplin des Profils zum Code (Label, Einheit, Richtung).
     *
     * @return array{code: string, label: string, unit: string|null, lower_is_better: bool}|null
     */
    public function discipline(string $code): ?array {
        $profile = $this->profile;
        foreach ($profile !== null ? ($profile->disciplines ?? []) : [] as $discipline) {
            if ((string) $discipline['code'] === $code) {
                return ['code' => $code, 'label' => (string) $discipline['label'], 'unit' => $discipline['unit'] ?? null, 'lower_is_better' => (bool) ($discipline['lower_is_better'] ?? false)];
            }
        }

        return null;
    }
}
