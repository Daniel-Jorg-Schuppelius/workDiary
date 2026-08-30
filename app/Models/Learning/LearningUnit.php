<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningUnit.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Learning;

use App\Enums\Learning\LearningUnitKind;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasAttachments, HasSqid};
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasOne};

/**
 * Lerneinheit (Feature 149): die kleinste abschließbare Einheit. `content`
 * trägt die Inhaltsblöcke bzw. den Zeiger auf die Fremdressource,
 * `completion_rule` das Abschlusskriterium und `release_rule` den
 * Freischaltplan.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $learning_course_id
 * @property int|null $learning_section_id
 * @property int|null $event_id
 * @property int|null $registration_lead_hours
 * @property int|null $cancellation_lead_hours
 * @property string $title
 * @property LearningUnitKind $kind
 * @property int $position
 * @property bool $is_mandatory
 * @property int $points
 * @property int|null $duration_minutes
 * @property string|null $content
 * @property array<string, mixed>|null $completion_rule
 * @property array<string, mixed>|null $release_rule
 */
class LearningUnit extends Model {
    use Auditable;

    use BelongsToOrganization;

    use HasAttachments;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'learning_course_id',
        'learning_section_id',
        'event_id',
        'title',
        'kind',
        'registration_lead_hours',
        'cancellation_lead_hours',
        'position',
        'is_mandatory',
        'points',
        'duration_minutes',
        'content',
        'completion_rule',
        'release_rule',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'kind' => LearningUnitKind::class,
        'position' => 'integer',
        'is_mandatory' => 'boolean',
        'points' => 'integer',
        'duration_minutes' => 'integer',
        'registration_lead_hours' => 'integer',
        'cancellation_lead_hours' => 'integer',
        'completion_rule' => 'array',
        'release_rule' => 'array',
    ];

    /** @return BelongsTo<LearningCourse, $this> */
    public function course(): BelongsTo {
        return $this->belongsTo(LearningCourse::class, 'learning_course_id');
    }

    /** @return BelongsTo<LearningSection, $this> */
    public function section(): BelongsTo {
        return $this->belongsTo(LearningSection::class, 'learning_section_id');
    }

    /** Prüfung dieser Einheit (nur bei kind = quiz). */
    /** @return HasOne<LearningQuiz, $this> */
    public function quiz(): HasOne {
        return $this->hasOne(LearningQuiz::class, 'learning_unit_id');
    }

    /** Präsenztermin dieser Einheit (nur bei kind = event). */
    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo {
        return $this->belongsTo(Event::class);
    }

    /** Aufgabe dieser Einheit (nur bei kind = assignment). */
    /** @return HasOne<LearningAssignment, $this> */
    public function assignment(): HasOne {
        return $this->hasOne(LearningAssignment::class, 'learning_unit_id');
    }

    /** @return HasOne<LearningScormPackage, $this> */
    public function scormPackage(): HasOne {
        return $this->hasOne(LearningScormPackage::class, 'learning_unit_id');
    }

    /**
     * Inhaltsblöcke der Einheit.
     *
     * @return list<array<string, mixed>>
     */
    public function blocks(): array {
        if ($this->content === null || $this->content === '') {
            return [];
        }

        $decoded = json_decode($this->content, true);

        return is_array($decoded) ? array_values($decoded) : [];
    }
}
