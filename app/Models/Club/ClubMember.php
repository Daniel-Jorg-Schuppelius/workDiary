<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubMember.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Club;

use App\Enums\Club\{ClubGroupMembershipStatus, ClubMembershipKind};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Platform\User;
use Carbon\CarbonInterface;
use Database\Factories\Club\ClubMemberFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * Vereinsmitglied (Feature 159, MVP-842): dauerhafte Personenidentität je
 * Organisation, unabhängig von einem Benutzerkonto. Die Mitgliedsnummer läuft
 * je Organisation und ist der Abgleichschlüssel des CSV-Imports; Art und
 * Pausen stehen im Verlauf ({@see ClubMembershipPeriod}), der aktuelle Stand
 * zusätzlich am Mitglied. Ein Austritt beendet die Gruppenzuordnungen,
 * frühere Nachweise bleiben erhalten.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $member_no
 * @property string $first_name
 * @property string $last_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $street
 * @property string|null $postal_code
 * @property string|null $city
 * @property Carbon|null $birth_date
 * @property ClubMembershipKind $kind
 * @property Carbon $joined_on
 * @property Carbon|null $left_on
 * @property int|null $user_id
 * @property string|null $notes
 * @property int|null $created_by_user_id
 */
class ClubMember extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<ClubMemberFactory> */
    use HasFactory;
    use HasSqid;

    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'member_no',
        'first_name',
        'last_name',
        'email',
        'phone',
        'street',
        'postal_code',
        'city',
        'birth_date',
        'kind',
        'joined_on',
        'left_on',
        'user_id',
        'notes',
        'created_by_user_id',
    ];

    protected $casts = [
        'member_no' => 'integer',
        'birth_date' => 'date',
        'kind' => ClubMembershipKind::class,
        'joined_on' => 'date',
        'left_on' => 'date',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return HasMany<ClubMembershipPeriod, $this> */
    public function periods(): HasMany {
        return $this->hasMany(ClubMembershipPeriod::class)->orderBy('starts_on');
    }

    /** @return HasMany<ClubGroupMembership, $this> */
    public function groupMemberships(): HasMany {
        return $this->hasMany(ClubGroupMembership::class);
    }

    /** @return HasMany<ClubGroupMembership, $this> */
    public function activeGroupMemberships(): HasMany {
        return $this->groupMemberships()->where('status', ClubGroupMembershipStatus::Active->value);
    }

    /** @return HasMany<ClubGuardian, $this> */
    public function guardians(): HasMany {
        return $this->hasMany(ClubGuardian::class);
    }

    /** @return HasMany<ClubEventParticipation, $this> */
    public function eventParticipations(): HasMany {
        return $this->hasMany(ClubEventParticipation::class);
    }

    /** @return HasMany<ClubAttendanceRecord, $this> */
    public function attendanceRecords(): HasMany {
        return $this->hasMany(ClubAttendanceRecord::class);
    }

    /** @return HasMany<ClubNotification, $this> */
    public function clubNotifications(): HasMany {
        return $this->hasMany(ClubNotification::class);
    }

    /** @return HasMany<ClubFeeAssignment, $this> */
    public function feeAssignments(): HasMany {
        return $this->hasMany(ClubFeeAssignment::class)->orderByDesc('valid_from');
    }

    /** @return HasMany<ClubFeeExemption, $this> */
    public function feeExemptions(): HasMany {
        return $this->hasMany(ClubFeeExemption::class)->orderByDesc('starts_on');
    }

    /** @return HasMany<ClubGroupChangeProposal, $this> */
    public function proposals(): HasMany {
        return $this->hasMany(ClubGroupChangeProposal::class);
    }

    /** @return HasMany<ClubSquadMember, $this> */
    public function squadMemberships(): HasMany {
        return $this->hasMany(ClubSquadMember::class);
    }

    /** @return HasMany<ClubPerformance, $this> */
    public function performances(): HasMany {
        return $this->hasMany(ClubPerformance::class);
    }

    /** @return HasMany<ClubStartRight, $this> */
    public function startRights(): HasMany {
        return $this->hasMany(ClubStartRight::class);
    }    public function fullName(): string {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /** Anzeige-Kennung im Register (z. B. "M-12"). */
    public function displayNo(): string {
        return 'M-' . $this->member_no;
    }

    /**
     * Alter in vollen Jahren am Stichtag; null ohne Geburtsdatum oder vor der
     * Geburt. Bewusst kalendarisch (Monat/Tag-Vergleich), nicht über Tageszahlen.
     */
    public function ageOn(CarbonInterface $date): ?int {
        if ($this->birth_date === null) {
            return null;
        }

        $age = $date->year - $this->birth_date->year;
        if ($date->format('md') < $this->birth_date->format('md')) {
            $age--;
        }

        return $age < 0 ? null : $age;
    }

    /** Ausgetreten zum Stichtag (Austrittsdatum ist der letzte Mitgliedstag). */
    public function hasLeftOn(CarbonInterface $date): bool {
        return $this->left_on !== null && $this->left_on->lessThan($date->copy()->startOfDay());
    }

    public function isCurrent(): bool {
        return ! $this->hasLeftOn(Carbon::today());
    }

    /**
     * Aktuelle Mitglieder: kein Austritt oder Austritt liegt nicht vor dem Stichtag.
     *
     * @param  Builder<ClubMember>  $query
     * @return Builder<ClubMember>
     */
    public function scopeCurrent(Builder $query, ?CarbonInterface $on = null): Builder {
        $date = ($on ?? Carbon::today())->toDateString();

        return $query->where(function (Builder $inner) use ($date): void {
            $inner->whereNull('left_on')->orWhere('left_on', '>=', $date);
        });
    }

    /**
     * @param  Builder<ClubMember>  $query
     * @return Builder<ClubMember>
     */
    public function scopeLeft(Builder $query, ?CarbonInterface $on = null): Builder {
        return $query->whereNotNull('left_on')->where('left_on', '<', ($on ?? Carbon::today())->toDateString());
    }

    /**
     * Suche über Name, E-Mail und Mitgliedsnummer (Nummer nur bei Ziffernfolge).
     *
     * @param  Builder<ClubMember>  $query
     * @return Builder<ClubMember>
     */
    public function scopeSearch(Builder $query, string $term): Builder {
        $term = trim($term);
        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($term): void {
            $inner->whereLikeEscaped('first_name', $term)
                ->orWhereLikeEscaped('last_name', $term)
                ->orWhereLikeEscaped('email', $term);
            if (ctype_digit($term)) {
                $inner->orWhere('member_no', (int) $term);
            }
        });
    }
}
