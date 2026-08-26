<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TrainingCourseVersion.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Training;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Database\Factories\Training\TrainingCourseVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Kursversion (Feature 145): welcher Stand wurde geschult. Die Version
 * wandert beim Nachweis in den Soll-Eintrag, damit später belegbar ist,
 * WELCHER Inhalt vermittelt wurde.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $training_course_id
 * @property int $version
 * @property string|null $label
 * @property Carbon|null $valid_from
 * @property string|null $content_summary
 * @property bool $is_current
 */
class TrainingCourseVersion extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<TrainingCourseVersionFactory> */
    use HasFactory;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'training_course_id',
        'version',
        'label',
        'valid_from',
        'content_summary',
        'is_current',
    ];

    protected $casts = [
        'version' => 'integer',
        'valid_from' => 'date',
        'is_current' => 'boolean',
    ];

    /** Anzeige-Kennung (z. B. "v3" oder "v3 · 2026-01"). */
    public function displayLabel(): string {
        return 'v' . $this->version . ($this->label !== null && $this->label !== '' ? ' · ' . $this->label : '');
    }

    /** @return BelongsTo<TrainingCourse, $this> */
    public function course(): BelongsTo {
        return $this->belongsTo(TrainingCourse::class, 'training_course_id');
    }
}
