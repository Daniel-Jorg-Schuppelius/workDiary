<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningSection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Learning;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Abschnitt eines Lernkurses (Feature 149) — die optionale mittlere Ebene.
 * Ein Kurs ohne Abschnitte hängt seine Einheiten direkt an den Kurs.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $learning_course_id
 * @property string $title
 * @property string|null $description
 * @property int $position
 */
class LearningSection extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'learning_course_id',
        'title',
        'description',
        'position',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'position' => 'integer',
    ];

    /** @return BelongsTo<LearningCourse, $this> */
    public function course(): BelongsTo {
        return $this->belongsTo(LearningCourse::class, 'learning_course_id');
    }

    /** @return HasMany<LearningUnit, $this> */
    public function units(): HasMany {
        return $this->hasMany(LearningUnit::class)->orderBy('position');
    }
}
