<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubGroup.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Club;

use App\Enums\Club\{ClubAdmissionMode, ClubGroupMembershipStatus, ClubProposalStatus};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use App\Support\Query\DateRange;
use Carbon\CarbonInterface;
use Database\Factories\Club\ClubGroupFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Trainings-/Vereinsgruppe (MVP-842): Alters-, Leistungs- oder Mischgruppe
 * mit Leitung, optionaler Obergrenze, Aufnahmemodus und inklusiven
 * Altersgrenzen (leer = offen). Gradkriterien kommen mit MVP-846.
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $club_department_id
 * @property string $name
 * @property string|null $description
 * @property int|null $leader_user_id
 * @property int|null $max_members
 * @property ClubAdmissionMode $admission_mode
 * @property int|null $min_age
 * @property int|null $max_age
 * @property string|null $criteria_note
 * @property bool $is_active
 */
class ClubGroup extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<ClubGroupFactory> */
    use HasFactory;
    use HasSqid;

    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'club_department_id',
        'name',
        'description',
        'leader_user_id',
        'max_members',
        'admission_mode',
        'min_age',
        'max_age',
        'criteria_note',
        'is_active',
    ];

    protected $casts = [
        'max_members' => 'integer',
        'admission_mode' => ClubAdmissionMode::class,
        'min_age' => 'integer',
        'max_age' => 'integer',
        'is_active' => 'boolean',
    ];

    /** @return BelongsTo<ClubDepartment, $this> */
    public function department(): BelongsTo {
        return $this->belongsTo(ClubDepartment::class, 'club_department_id');
    }

    /** @return BelongsTo<User, $this> */
    public function leader(): BelongsTo {
        return $this->belongsTo(User::class, 'leader_user_id');
    }

    /** @return HasMany<ClubGroupMembership, $this> */
    public function memberships(): HasMany {
        return $this->hasMany(ClubGroupMembership::class);
    }

    /** @return HasMany<ClubGroupMembership, $this> */
    public function activeMemberships(): HasMany {
        return $this->memberships()->where('status', ClubGroupMembershipStatus::Active->value);
    }

    /** @return HasMany<ClubGroupMembership, $this> */
    public function requestedMemberships(): HasMany {
        return $this->memberships()->where('status', ClubGroupMembershipStatus::Requested->value);
    }

    /** @return HasMany<ClubGroupChangeProposal, $this> */
    public function proposals(): HasMany {
        return $this->hasMany(ClubGroupChangeProposal::class);
    }

    /** @return HasMany<ClubGroupChangeProposal, $this> */
    public function openProposals(): HasMany {
        return $this->proposals()->where('status', ClubProposalStatus::Open->value);
    }

    public function isLedBy(?User $user): bool {
        return $user !== null && $this->leader_user_id !== null && $this->leader_user_id === $user->id;
    }

    public function hasAgeCriteria(): bool {
        return $this->min_age !== null || $this->max_age !== null;
    }

    /** Belegte Plätze am Stichtag (aktive Zuordnungen, gültig am Tag). */
    public function activeMemberCountOn(CarbonInterface $date): int {
        return $this->activeMemberships()
            ->where('valid_from', '<', DateRange::dayAfter($date))
            ->where(function (Builder $query) use ($date): void {
                $query->whereNull('valid_to')->orWhere('valid_to', '>=', DateRange::day($date));
            })
            ->count();
    }

    public function hasCapacityOn(CarbonInterface $date): bool {
        return $this->max_members === null || $this->activeMemberCountOn($date) < $this->max_members;
    }

    /** Lesbare Altersgrenzen („6–11 Jahre", „ab 18 Jahren", „bis 17 Jahre") oder null. */
    public function ageRangeLabel(): ?string {
        return match (true) {
            $this->min_age !== null && $this->max_age !== null => (string) __('club.age_range.between', ['min' => $this->min_age, 'max' => $this->max_age]),
            $this->min_age !== null => (string) __('club.age_range.from', ['min' => $this->min_age]),
            $this->max_age !== null => (string) __('club.age_range.until', ['max' => $this->max_age]),
            default => null,
        };
    }
}
