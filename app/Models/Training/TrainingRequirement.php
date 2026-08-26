<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TrainingRequirement.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Training;

use App\Enums\Training\TrainingRequirementSubject;
use App\Enums\User\UserRole;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Team;
use Database\Factories\Training\TrainingRequirementFactory;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Zeile der Pflichtmatrix (Feature 145): Rolle bzw. Tätigkeitsbereich
 * (Team) × Kurs. Der Abgleich erzeugt daraus Soll-Einträge je
 * Mitarbeitendem ({@see TrainingAssignment}); die Zuordnung selbst sperrt
 * nichts — die Sperrwirkung bleibt beim Qualifikationsstatus (Feature 013).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $training_course_id
 * @property TrainingRequirementSubject $subject_kind
 * @property string $subject_key
 * @property int $first_due_days
 * @property bool $is_active
 * @property string $source
 * @property string|null $note
 */
class TrainingRequirement extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<TrainingRequirementFactory> */
    use HasFactory;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'training_course_id',
        'subject_kind',
        'subject_key',
        'first_due_days',
        'is_active',
        'source',
        'note',
    ];

    protected $casts = [
        'subject_kind' => TrainingRequirementSubject::class,
        'first_due_days' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Anzeigename der Zielgruppe — Rollen-Label bzw. Teamname (Label-Helfer,
     * nie den rohen Slug/Schlüssel in Views).
     */
    public function subjectLabel(): string {
        if ($this->subject_kind === TrainingRequirementSubject::Role) {
            return UserRole::tryFrom($this->subject_key)?->label() ?? $this->subject_key;
        }

        $team = $this->team();

        return $team instanceof Team ? $team->name : $this->subject_key;
    }

    /** Team der Zielgruppe (nur bei subject_kind=team). */
    public function team(): ?Team {
        if ($this->subject_kind !== TrainingRequirementSubject::Team) {
            return null;
        }

        return Team::query()->find((int) $this->subject_key);
    }

    /** @return BelongsTo<TrainingCourse, $this> */
    public function course(): BelongsTo {
        return $this->belongsTo(TrainingCourse::class, 'training_course_id');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder {
        return $query->where('is_active', true);
    }
}
