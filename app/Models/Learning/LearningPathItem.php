<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningPathItem.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Learning;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Station eines Lernpfades (Feature 149, MVP-745).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $learning_path_id
 * @property int $learning_course_id
 * @property int $position
 * @property bool $is_mandatory
 * @property int|null $due_days
 */
class LearningPathItem extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'learning_path_id',
        'learning_course_id',
        'position',
        'is_mandatory',
        'due_days',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'position' => 'integer',
        'is_mandatory' => 'boolean',
        'due_days' => 'integer',
    ];

    /** @return BelongsTo<LearningPath, $this> */
    public function path(): BelongsTo {
        return $this->belongsTo(LearningPath::class, 'learning_path_id');
    }

    /** @return BelongsTo<LearningCourse, $this> */
    public function course(): BelongsTo {
        return $this->belongsTo(LearningCourse::class, 'learning_course_id');
    }
}
