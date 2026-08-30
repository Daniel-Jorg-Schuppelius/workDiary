<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningAssignment.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Learning;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Aufgabe an einer Lerneinheit (Feature 149, MVP-739). Die Rubrik ist eine
 * Liste von Kriterien mit Gewicht — sie macht eine Bewertung erklärbar
 * statt zu einer Bauchzahl.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $learning_unit_id
 * @property string $title
 * @property string|null $instructions
 * @property string $submission_kind
 * @property int|null $due_days
 * @property int $points
 * @property int $pass_percent
 * @property list<array{key: string, label: string, weight: int, max_points: int}>|null $rubric
 * @property bool $requires_second_opinion
 * @property-read LearningUnit|null $unit
 */
class LearningAssignment extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'learning_unit_id',
        'title',
        'instructions',
        'submission_kind',
        'due_days',
        'points',
        'pass_percent',
        'rubric',
        'requires_second_opinion',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'due_days' => 'integer',
        'points' => 'integer',
        'pass_percent' => 'integer',
        'rubric' => 'array',
        'requires_second_opinion' => 'boolean',
    ];

    /** @return BelongsTo<LearningUnit, $this> */
    public function unit(): BelongsTo {
        return $this->belongsTo(LearningUnit::class, 'learning_unit_id');
    }

    /** @return HasMany<LearningSubmission, $this> */
    public function submissions(): HasMany {
        return $this->hasMany(LearningSubmission::class, 'learning_assignment_id');
    }

    /**
     * Kriterien der Rubrik (leere Liste = freie Bewertung ohne Raster).
     *
     * @return list<array<string, mixed>>
     */
    public function criteria(): array {
        return is_array($this->rubric) ? $this->rubric : [];
    }

    public function requiresFile(): bool {
        return $this->submission_kind === 'file' || $this->submission_kind === 'both';
    }

    public function requiresText(): bool {
        return $this->submission_kind === 'text' || $this->submission_kind === 'both';
    }
}
