<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubMemberGrade.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Club;

use App\Enums\Club\ClubGradeSource;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Platform\User;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Vergebener oder anerkannter Grad eines Mitglieds (MVP-846/847): Datum,
 * Herkunft, Beleg, bestätigende Person; Widerruf als eigener begründeter Vorgang.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $club_member_id
 * @property int $club_grading_system_id
 * @property int $club_grade_id
 * @property Carbon $obtained_on
 * @property ClubGradeSource $source
 * @property int|null $club_exam_candidate_id
 * @property string|null $evidence
 * @property int|null $confirmed_by_user_id
 * @property Carbon|null $revoked_at
 * @property int|null $revoked_by_user_id
 * @property string|null $revoke_reason
 */
class ClubMemberGrade extends Model {
    use Auditable;

    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'club_member_id', 'club_grading_system_id', 'club_grade_id', 'obtained_on', 'source',
        'club_exam_candidate_id', 'evidence', 'confirmed_by_user_id', 'revoked_at', 'revoked_by_user_id', 'revoke_reason',
    ];

    protected $casts = [
        'obtained_on' => 'date',
        'source' => ClubGradeSource::class,
        'revoked_at' => 'datetime',
    ];

    /** @return BelongsTo<ClubMember, $this> */
    public function member(): BelongsTo {
        return $this->belongsTo(ClubMember::class, 'club_member_id');
    }

    /** @return BelongsTo<ClubGradingSystem, $this> */
    public function system(): BelongsTo {
        return $this->belongsTo(ClubGradingSystem::class, 'club_grading_system_id');
    }

    /** @return BelongsTo<ClubGrade, $this> */
    public function grade(): BelongsTo {
        return $this->belongsTo(ClubGrade::class, 'club_grade_id');
    }

    /** @return BelongsTo<User, $this> */
    public function confirmedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'confirmed_by_user_id');
    }

    public function isRevoked(): bool {
        return $this->revoked_at !== null;
    }

    /**
     * @param  Builder<ClubMemberGrade>  $query
     * @return Builder<ClubMemberGrade>
     */
    public function scopeValid(Builder $query): Builder {
        return $query->whereNull($query->qualifyColumn('revoked_at'));
    }
}
