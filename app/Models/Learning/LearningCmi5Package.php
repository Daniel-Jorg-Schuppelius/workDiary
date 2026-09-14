<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningCmi5Package.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Learning;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Importierter cmi5-Kurs an einer Lerneinheit.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $learning_unit_id
 * @property string $title
 * @property string $course_id
 * @property string $activity_id
 * @property list<array{publisher_id: string, activity_id: string, title: string, units: list<string>}>|null $blocks
 * @property string|null $storage_path
 * @property string $structure_hash
 * @property int $file_count
 * @property int $size_bytes
 */
class LearningCmi5Package extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'learning_unit_id',
        'title',
        'course_id',
        'activity_id',
        'blocks',
        'storage_path',
        'structure_hash',
        'file_count',
        'size_bytes',
        'uploaded_by_user_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'blocks' => 'array',
        'file_count' => 'integer',
        'size_bytes' => 'integer',
    ];

    /** @return BelongsTo<LearningUnit, $this> */
    public function unit(): BelongsTo {
        return $this->belongsTo(LearningUnit::class, 'learning_unit_id');
    }

    /** @return HasMany<LearningCmi5Unit, $this> */
    public function units(): HasMany {
        return $this->hasMany(LearningCmi5Unit::class, 'learning_cmi5_package_id')->orderBy('position');
    }

    /** @return HasMany<LearningCmi5Registration, $this> */
    public function registrations(): HasMany {
        return $this->hasMany(LearningCmi5Registration::class, 'learning_cmi5_package_id');
    }
}
