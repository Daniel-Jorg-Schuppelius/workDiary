<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningCmi5AuState.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Learning;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Registrierungsweiter Stand einer AU: abgeschlossen, bestanden, gescheitert,
 * erlassen, erfüllt. Gilt über Sitzungen hinweg.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $learning_cmi5_registration_id
 * @property int $learning_cmi5_unit_id
 * @property Carbon|null $completed_at
 * @property Carbon|null $passed_at
 * @property Carbon|null $failed_at
 * @property Carbon|null $waived_at
 * @property Carbon|null $satisfied_at
 */
class LearningCmi5AuState extends Model {
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'learning_cmi5_registration_id',
        'learning_cmi5_unit_id',
        'completed_at',
        'passed_at',
        'failed_at',
        'waived_at',
        'satisfied_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'completed_at' => 'datetime',
        'passed_at' => 'datetime',
        'failed_at' => 'datetime',
        'waived_at' => 'datetime',
        'satisfied_at' => 'datetime',
    ];

    /** @return BelongsTo<LearningCmi5Registration, $this> */
    public function registration(): BelongsTo {
        return $this->belongsTo(LearningCmi5Registration::class, 'learning_cmi5_registration_id');
    }

    /** @return BelongsTo<LearningCmi5Unit, $this> */
    public function unit(): BelongsTo {
        return $this->belongsTo(LearningCmi5Unit::class, 'learning_cmi5_unit_id');
    }
}
