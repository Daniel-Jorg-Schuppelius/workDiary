<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningCmi5Registration.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Learning;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * Registrierung einer Einschreibung für einen cmi5-Kurs (cmi5 9.6.1). Die
 * Kennung wandert in jede Start-URL und in jedes Statement.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $learning_enrollment_id
 * @property int $learning_cmi5_package_id
 * @property string $registration
 * @property Carbon|null $satisfied_at
 * @property-read LearningEnrollment|null $enrollment
 * @property-read LearningCmi5Package|null $package
 */
class LearningCmi5Registration extends Model {
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'learning_enrollment_id',
        'learning_cmi5_package_id',
        'registration',
        'satisfied_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'satisfied_at' => 'datetime',
    ];

    /** @return BelongsTo<LearningEnrollment, $this> */
    public function enrollment(): BelongsTo {
        return $this->belongsTo(LearningEnrollment::class, 'learning_enrollment_id');
    }

    /** @return BelongsTo<LearningCmi5Package, $this> */
    public function package(): BelongsTo {
        return $this->belongsTo(LearningCmi5Package::class, 'learning_cmi5_package_id');
    }

    /** @return HasMany<LearningCmi5AuState, $this> */
    public function states(): HasMany {
        return $this->hasMany(LearningCmi5AuState::class, 'learning_cmi5_registration_id');
    }
}
