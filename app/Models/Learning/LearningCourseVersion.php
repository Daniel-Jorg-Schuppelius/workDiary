<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningCourseVersion.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Learning;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Training\TrainingCourseVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Eingefrorene Inhaltsversion eines Lernkurses (Feature 149). Ohne den
 * Schnappschuss ließe sich ein Nachweis nach einer Kursänderung nicht mehr
 * erklären; laufende Einschreibungen bleiben deshalb auf ihrer Version.
 *
 * `training_course_version_id` ist der Spiegel in Feature 145 — geschrieben
 * wird ausschließlich in diese Richtung.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $learning_course_id
 * @property int $version
 * @property string|null $label
 * @property string|null $content_snapshot
 * @property Carbon|null $released_at
 * @property int|null $released_by_user_id
 * @property bool $is_current
 * @property int|null $training_course_version_id
 */
class LearningCourseVersion extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'learning_course_id',
        'version',
        'label',
        'content_snapshot',
        'released_at',
        'released_by_user_id',
        'is_current',
        'training_course_version_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'version' => 'integer',
        'released_at' => 'datetime',
        'is_current' => 'boolean',
    ];

    /** @return BelongsTo<LearningCourse, $this> */
    public function course(): BelongsTo {
        return $this->belongsTo(LearningCourse::class, 'learning_course_id');
    }

    /** @return BelongsTo<TrainingCourseVersion, $this> */
    public function trainingCourseVersion(): BelongsTo {
        return $this->belongsTo(TrainingCourseVersion::class);
    }

    /** @return BelongsTo<User, $this> */
    public function releasedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'released_by_user_id');
    }

    /**
     * Inhaltsbaum des Schnappschusses.
     *
     * @return array<string, mixed>
     */
    public function snapshot(): array {
        if ($this->content_snapshot === null || $this->content_snapshot === '') {
            return [];
        }

        $decoded = json_decode($this->content_snapshot, true);

        return is_array($decoded) ? $decoded : [];
    }
}
