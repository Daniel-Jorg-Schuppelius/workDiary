<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubExamCandidate.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Club;

use App\Enums\Club\ClubExamCandidateStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany};
use Illuminate\Support\Carbon;

/**
 * Prüfungskandidat (MVP-847): Mitglied × Angebot × Zielgrad mit Zulassungs-
 * bericht (Schnappschuss), fachlicher Freigabe, Ausnahme, Überprüfungsmarke
 * und Ergebnis; die verwendeten Nachweise bleiben referenziert.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $club_exam_offer_id
 * @property int $club_member_id
 * @property int $target_grade_id
 * @property ClubExamCandidateStatus $status
 * @property Carbon|null $requested_at
 * @property int|null $requested_by_user_id
 * @property Carbon|null $admitted_at
 * @property int|null $admitted_by_user_id
 * @property Carbon|null $approved_at
 * @property int|null $approved_by_user_id
 * @property string|null $exception_reason
 * @property int|null $exception_by_user_id
 * @property bool|null $eligibility_met
 * @property array<string, mixed>|null $eligibility_report
 * @property Carbon|null $checked_at
 * @property Carbon|null $review_required_at
 * @property string|null $review_note
 * @property Carbon|null $result_recorded_at
 * @property int|null $result_by_user_id
 * @property string|null $result_note
 * @property int|null $awarded_member_grade_id
 */
class ClubExamCandidate extends Model {
    use Auditable;

    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'club_exam_offer_id', 'club_member_id', 'target_grade_id', 'status',
        'requested_at', 'requested_by_user_id', 'admitted_at', 'admitted_by_user_id', 'approved_at', 'approved_by_user_id',
        'exception_reason', 'exception_by_user_id', 'eligibility_met', 'eligibility_report', 'checked_at',
        'review_required_at', 'review_note', 'result_recorded_at', 'result_by_user_id', 'result_note', 'awarded_member_grade_id',
    ];

    protected $casts = [
        'status' => ClubExamCandidateStatus::class,
        'requested_at' => 'datetime',
        'admitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'eligibility_met' => 'boolean',
        'eligibility_report' => 'array',
        'checked_at' => 'datetime',
        'review_required_at' => 'datetime',
        'result_recorded_at' => 'datetime',
    ];

    /** @return BelongsTo<ClubExamOffer, $this> */
    public function offer(): BelongsTo {
        return $this->belongsTo(ClubExamOffer::class, 'club_exam_offer_id');
    }

    /** @return BelongsTo<ClubMember, $this> */
    public function member(): BelongsTo {
        return $this->belongsTo(ClubMember::class, 'club_member_id');
    }

    /** @return BelongsTo<ClubGrade, $this> */
    public function targetGrade(): BelongsTo {
        return $this->belongsTo(ClubGrade::class, 'target_grade_id');
    }

    /** @return BelongsTo<ClubMemberGrade, $this> */
    public function awardedGrade(): BelongsTo {
        return $this->belongsTo(ClubMemberGrade::class, 'awarded_member_grade_id');
    }

    /** @return BelongsTo<User, $this> */
    public function resultBy(): BelongsTo {
        return $this->belongsTo(User::class, 'result_by_user_id');
    }

    /** @return BelongsToMany<ClubAttendanceRecord, $this> */
    public function usedRecords(): BelongsToMany {
        return $this->belongsToMany(ClubAttendanceRecord::class, 'club_exam_candidate_records', 'club_exam_candidate_id', 'club_attendance_record_id');
    }

    public function hasException(): bool {
        return $this->exception_reason !== null;
    }

    public function needsReview(): bool {
        return $this->review_required_at !== null;
    }
}
