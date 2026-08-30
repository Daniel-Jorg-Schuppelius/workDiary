<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningEnrollment.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Learning;

use App\Enums\Learning\{LearningEnrollmentSource, LearningEnrollmentStatus};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\{ExternalParticipant, User};
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * Einschreibung in einen Lernkurs (Feature 149, MVP-737).
 *
 * Genau eine Subjektart je Zeile: `user_id` (interne Mitarbeitende und
 * Portal-Kunden — beide in `users`, getrennt über den Guard) oder
 * `external_participant_id` (Lernende ohne Konto). Die Einschreibung hängt
 * an der Kursversion, damit sich der Stoff unter laufenden Teilnehmern
 * nicht ändert.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $learning_course_id
 * @property int|null $learning_course_version_id
 * @property int|null $user_id
 * @property int|null $external_participant_id
 * @property LearningEnrollmentStatus $status
 * @property LearningEnrollmentSource $source
 * @property int|null $assigned_by_user_id
 * @property Carbon|null $due_at
 * @property Carbon|null $access_until
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property int|null $score_percent
 * @property int $points_earned
 * @property-read User|null $user
 * @property-read ExternalParticipant|null $externalParticipant
 * @property-read LearningCourse|null $course
 * @property-read LearningCourseVersion|null $courseVersion
 */
class LearningEnrollment extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'learning_course_id',
        'learning_course_version_id',
        'user_id',
        'external_participant_id',
        'status',
        'source',
        'assigned_by_user_id',
        'due_at',
        'access_until',
        'started_at',
        'completed_at',
        'score_percent',
        'points_earned',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'status' => LearningEnrollmentStatus::class,
        'source' => LearningEnrollmentSource::class,
        'due_at' => 'date:Y-m-d',
        'access_until' => 'date:Y-m-d',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'score_percent' => 'integer',
        'points_earned' => 'integer',
    ];

    /** @return BelongsTo<LearningCourse, $this> */
    public function course(): BelongsTo {
        return $this->belongsTo(LearningCourse::class, 'learning_course_id');
    }

    /** @return BelongsTo<LearningCourseVersion, $this> */
    public function courseVersion(): BelongsTo {
        return $this->belongsTo(LearningCourseVersion::class, 'learning_course_version_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ExternalParticipant, $this> */
    public function externalParticipant(): BelongsTo {
        return $this->belongsTo(ExternalParticipant::class);
    }

    /** @return HasMany<LearningEnrollmentEvent, $this> */
    public function events(): HasMany {
        return $this->hasMany(LearningEnrollmentEvent::class);
    }

    /** @return HasMany<LearningUnitProgress, $this> */
    public function progress(): HasMany {
        return $this->hasMany(LearningUnitProgress::class);
    }

    /** Anzeigename der lernenden Person, unabhängig von der Subjektart. */
    public function learnerName(): string {
        // `??` wertet die linke Seite im isset-Kontext aus — der
        // Nullsafe-Operator wäre hier doppelt gemoppelt.
        return $this->user->name ?? $this->externalParticipant->name ?? '';
    }

    /** Zugang abgelaufen? Danach bleibt nur die Nachweis-Ansicht. */
    public function isAccessExpired(): bool {
        return $this->access_until !== null && $this->access_until->isPast();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOpen(Builder $query): Builder {
        return $query->whereIn('status', [
            LearningEnrollmentStatus::Assigned->value,
            LearningEnrollmentStatus::InProgress->value,
        ]);
    }
}
