<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SafetyInstruction.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Safety;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Training\{TrainingCourse, TrainingCourseVersion};
use App\Models\User;
use Database\Factories\Safety\SafetyInstructionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * Unterweisung (DGUV Vorschrift 1 § 4, Feature 132): Thema, Datum,
 * unterweisende Person, optionaler Bezug zur Gefährdungsbeurteilung und
 * Wiederholungsintervall in Monaten. Der Nachweis je Person liegt in
 * {@see SafetyInstructionParticipant} (Signatur + next_due_on).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $instruction_no
 * @property string $topic
 * @property int|null $hazard_assessment_id
 * @property int|null $training_course_id
 * @property int|null $training_course_version_id
 * @property int|null $asset_id
 * @property Carbon $held_on
 * @property int|null $instructor_user_id
 * @property int|null $repeat_interval_months
 * @property string|null $notes
 * @property int|null $created_by_user_id
 */
class SafetyInstruction extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<SafetyInstructionFactory> */
    use HasFactory;
    use HasSqid;

    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'instruction_no',
        'topic',
        'hazard_assessment_id',
        'training_course_id',
        'training_course_version_id',
        'asset_id',
        'held_on',
        'instructor_user_id',
        'repeat_interval_months',
        'notes',
        'created_by_user_id',
    ];

    protected $casts = [
        'instruction_no' => 'integer',
        'held_on' => 'date',
        'repeat_interval_months' => 'integer',
    ];

    /** Anzeige-Kennung im Register (z. B. "UW-7"). */
    public function displayNo(): string {
        return 'UW-' . $this->instruction_no;
    }

    /** Nächste Fälligkeit aus Datum + Intervall (null ohne Intervall). */
    public function nextDueOn(): ?Carbon {
        if ($this->repeat_interval_months === null || $this->repeat_interval_months < 1) {
            return null;
        }

        return $this->held_on->copy()->addMonthsNoOverflow($this->repeat_interval_months);
    }

    /** @return HasMany<SafetyInstructionParticipant, $this> */
    public function participants(): HasMany {
        return $this->hasMany(SafetyInstructionParticipant::class);
    }

    /** @return BelongsTo<HazardAssessment, $this> */
    public function assessment(): BelongsTo {
        return $this->belongsTo(HazardAssessment::class, 'hazard_assessment_id');
    }

    /**
     * Kurs des Schulungskatalogs (Feature 145) — erst dieser Bezug macht die
     * Teilnahme zum Nachweis für ein Trainings-Soll.
     *
     * @return BelongsTo<TrainingCourse, $this>
     */
    public function trainingCourse(): BelongsTo {
        return $this->belongsTo(TrainingCourse::class, 'training_course_id');
    }

    /** @return BelongsTo<TrainingCourseVersion, $this> */
    public function trainingCourseVersion(): BelongsTo {
        return $this->belongsTo(TrainingCourseVersion::class, 'training_course_version_id');
    }

    /** @return BelongsTo<User, $this> */
    public function instructor(): BelongsTo {
        return $this->belongsTo(User::class, 'instructor_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
