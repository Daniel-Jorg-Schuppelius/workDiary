<?php
/*
 * Created on   : Tue Sep 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningGradebookComponent.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Learning;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Notenbuch-Komponente (Feature 149, MVP-790): eine Prüfung, eine Aufgabe
 * oder eine manuell vergebene Note — mit optionalem Gewicht. Gibt es für
 * einen Kurs Komponenten, rechnet das Notenbuch gewichtet; sonst wie bisher.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $learning_course_id
 * @property string $kind
 * @property int|null $learning_unit_id
 * @property string $title
 * @property int|null $weight_percent
 * @property int|null $max_points
 * @property int $position
 * @property-read LearningUnit|null $unit
 */
class LearningGradebookComponent extends Model {
    use Auditable;

    use BelongsToOrganization;

    use HasSqid;

    public const KIND_QUIZ = 'quiz';

    public const KIND_ASSIGNMENT = 'assignment';

    public const KIND_MANUAL = 'manual';

    protected $fillable = [
        'organization_id',
        'learning_course_id',
        'kind',
        'learning_unit_id',
        'title',
        'weight_percent',
        'max_points',
        'position',
    ];

    protected $casts = [
        'weight_percent' => 'integer',
        'max_points' => 'integer',
        'position' => 'integer',
    ];

    /** @return BelongsTo<LearningCourse, $this> */
    public function course(): BelongsTo {
        return $this->belongsTo(LearningCourse::class, 'learning_course_id');
    }

    /** @return BelongsTo<LearningUnit, $this> */
    public function unit(): BelongsTo {
        return $this->belongsTo(LearningUnit::class, 'learning_unit_id');
    }

    /** @return HasMany<LearningManualGrade, $this> */
    public function manualGrades(): HasMany {
        return $this->hasMany(LearningManualGrade::class, 'learning_gradebook_component_id');
    }

    public function isManual(): bool {
        return $this->kind === self::KIND_MANUAL;
    }
}
