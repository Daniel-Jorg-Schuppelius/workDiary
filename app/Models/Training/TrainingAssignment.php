<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TrainingAssignment.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Training;

use App\Enums\Training\TrainingAssignmentState;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Safety\{SafetyInstruction, SafetyInstructionParticipant};
use App\Models\User;
use Database\Factories\Training\TrainingAssignmentFactory;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Soll-Eintrag je Mitarbeitendem und Kurs (Feature 145) — genau EINE Zeile
 * je (Person, Kurs): `due_at` wandert nach jedem Nachweis um die Gültigkeit
 * des Kurses weiter, `fulfilled_at` trägt den letzten Nachweis. Der Nachweis
 * selbst liegt im Arbeitsschutz-Register (Feature 132); hier stehen nur die
 * Zeiger darauf, keine Signatur-Kopie.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $user_id
 * @property int $training_course_id
 * @property int|null $training_requirement_id
 * @property string $source
 * @property Carbon|null $due_at
 * @property Carbon|null $notify_from
 * @property Carbon|null $fulfilled_at
 * @property int|null $fulfilled_participant_id
 * @property int|null $fulfilled_instruction_id
 * @property int|null $fulfilled_course_version
 */
class TrainingAssignment extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<TrainingAssignmentFactory> */
    use HasFactory;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'user_id',
        'training_course_id',
        'training_requirement_id',
        'source',
        'due_at',
        'notify_from',
        'fulfilled_at',
        'fulfilled_participant_id',
        'fulfilled_instruction_id',
        'fulfilled_course_version',
    ];

    protected $casts = [
        'due_at' => 'date',
        'notify_from' => 'date',
        'fulfilled_at' => 'date',
        'fulfilled_course_version' => 'integer',
    ];

    /**
     * Abgeleiteter Zustand zum Stichtag: überfällig > fällig (im Vorlauf) >
     * erfüllt (Nachweis vorhanden) > geplant.
     */
    public function state(?Carbon $today = null): TrainingAssignmentState {
        $today = ($today ?? Carbon::today())->startOfDay();

        if ($this->due_at === null) {
            return TrainingAssignmentState::Fulfilled;
        }
        if ($this->due_at->startOfDay()->lt($today)) {
            return TrainingAssignmentState::Overdue;
        }
        if ($this->notify_from !== null && ! $this->notify_from->startOfDay()->gt($today)) {
            return TrainingAssignmentState::Due;
        }

        return $this->fulfilled_at !== null ? TrainingAssignmentState::Fulfilled : TrainingAssignmentState::Planned;
    }

    /** Erfüllungsgrad-Zählung: gilt der Soll-Eintrag zum Stichtag als erfüllt? */
    public function isCompliant(?Carbon $today = null): bool {
        return $this->state($today) !== TrainingAssignmentState::Overdue;
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<TrainingCourse, $this> */
    public function course(): BelongsTo {
        return $this->belongsTo(TrainingCourse::class, 'training_course_id');
    }

    /** @return BelongsTo<TrainingRequirement, $this> */
    public function requirement(): BelongsTo {
        return $this->belongsTo(TrainingRequirement::class, 'training_requirement_id');
    }

    /** @return BelongsTo<SafetyInstructionParticipant, $this> */
    public function participant(): BelongsTo {
        return $this->belongsTo(SafetyInstructionParticipant::class, 'fulfilled_participant_id');
    }

    /** @return BelongsTo<SafetyInstruction, $this> */
    public function instruction(): BelongsTo {
        return $this->belongsTo(SafetyInstruction::class, 'fulfilled_instruction_id');
    }

    /**
     * Offene Soll-Einträge (Termin gesetzt).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOpen(Builder $query): Builder {
        return $query->whereNotNull('due_at');
    }

    /**
     * Überfällige Soll-Einträge zum Stichtag.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOverdue(Builder $query): Builder {
        return $query->whereNotNull('due_at')->where('due_at', '<', Carbon::today()->toDateString());
    }
}
