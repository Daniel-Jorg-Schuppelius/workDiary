<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningSubmission.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Learning;

use App\Enums\Learning\LearningSubmissionStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasAttachments, HasSqid};
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Abgabe zu einer Aufgabe (Feature 149, MVP-739). Dateien hängen über die
 * vorhandene Anhang-Mechanik daran — keine zweite Ablage.
 *
 * `rubric_snapshot` friert die Kriterien der Bewertung ein: eine später
 * geänderte Rubrik darf eine alte Bewertung nicht umdeuten.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $learning_assignment_id
 * @property int $learning_enrollment_id
 * @property LearningSubmissionStatus $status
 * @property string|null $body
 * @property Carbon|null $submitted_at
 * @property Carbon|null $graded_at
 * @property int|null $graded_by_user_id
 * @property int|null $points_awarded
 * @property int|null $score_percent
 * @property bool|null $passed
 * @property string|null $feedback
 * @property array<string, int>|null $rubric_scores
 * @property list<array<string, mixed>>|null $rubric_snapshot
 * @property int|null $second_opinion_by_user_id
 * @property Carbon|null $second_opinion_at
 * @property int $attempt_no
 * @property-read LearningAssignment|null $assignment
 * @property-read LearningEnrollment|null $enrollment
 */
class LearningSubmission extends Model {
    use Auditable;

    use BelongsToOrganization;
    use HasAttachments;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'learning_assignment_id',
        'learning_enrollment_id',
        'status',
        'body',
        'submitted_at',
        'graded_at',
        'graded_by_user_id',
        'points_awarded',
        'score_percent',
        'passed',
        'feedback',
        'rubric_scores',
        'rubric_snapshot',
        'second_opinion_by_user_id',
        'second_opinion_at',
        'attempt_no',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'status' => LearningSubmissionStatus::class,
        'submitted_at' => 'datetime',
        'graded_at' => 'datetime',
        'second_opinion_at' => 'datetime',
        'points_awarded' => 'integer',
        'score_percent' => 'integer',
        'passed' => 'boolean',
        'rubric_scores' => 'array',
        'rubric_snapshot' => 'array',
        'attempt_no' => 'integer',
    ];

    /** @return BelongsTo<LearningAssignment, $this> */
    public function assignment(): BelongsTo {
        return $this->belongsTo(LearningAssignment::class, 'learning_assignment_id');
    }

    /** @return BelongsTo<LearningEnrollment, $this> */
    public function enrollment(): BelongsTo {
        return $this->belongsTo(LearningEnrollment::class, 'learning_enrollment_id');
    }

    /** @return BelongsTo<User, $this> */
    public function gradedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'graded_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function secondOpinionBy(): BelongsTo {
        return $this->belongsTo(User::class, 'second_opinion_by_user_id');
    }

    /** Wartet auf Bewertung? */
    public function isPending(): bool {
        return $this->status === LearningSubmissionStatus::Submitted;
    }

    /**
     * Bewertung endgültig? Bei Vier-Augen-Pflicht erst nach der
     * Zweitbewertung.
     */
    public function isFinal(): bool {
        if ($this->status !== LearningSubmissionStatus::Graded) {
            return false;
        }

        return ! ($this->assignment->requires_second_opinion ?? false) || $this->second_opinion_at !== null;
    }
}
