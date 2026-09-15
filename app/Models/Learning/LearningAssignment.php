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
 * @property list<string>|null $allowed_extensions
 * @property int|null $max_files
 * @property int|null $max_file_mb
 * @property bool $auto_approve
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
        'allowed_extensions',
        'max_files',
        'max_file_mb',
        'auto_approve',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'due_days' => 'integer',
        'points' => 'integer',
        'pass_percent' => 'integer',
        'rubric' => 'array',
        'requires_second_opinion' => 'boolean',
        'allowed_extensions' => 'array',
        'max_files' => 'integer',
        'max_file_mb' => 'integer',
        'auto_approve' => 'boolean',
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

    /**
     * Dateiregeln (MVP-788) — immer ZUSÄTZLICH zu `FileAttacher::rule()`,
     * nie lockerer: erlaubte Endungen (leer = alle des Anhangsystems),
     * Anzahl (Standard 5) und Größe je Datei in KB (Standard = Systemgrenze).
     *
     * @return list<string>
     */
    public function allowedExtensions(): array {
        return array_values(array_filter(array_map(
            static fn (mixed $e): string => strtolower(ltrim(trim((string) $e), '.')),
            $this->allowed_extensions ?? [],
        ), static fn (string $e): bool => $e !== ''));
    }

    public function maxFiles(): int {
        return max(1, min(5, (int) ($this->max_files ?? 5)));
    }

    public function maxFileKb(int $systemKb): int {
        return $this->max_file_mb !== null && $this->max_file_mb > 0
            ? min($systemKb, $this->max_file_mb * 1024)
            : $systemKb;
    }

    public function requiresFile(): bool {
        return $this->submission_kind === 'file' || $this->submission_kind === 'both';
    }

    public function requiresText(): bool {
        return $this->submission_kind === 'text' || $this->submission_kind === 'both';
    }
}
