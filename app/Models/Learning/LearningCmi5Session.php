<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningCmi5Session.php
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
 * Eine AU-Sitzung (cmi5 9.6.3.1). Fetch- und Auth-Token liegen nur als Abdruck vor.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $learning_cmi5_registration_id
 * @property int $learning_cmi5_unit_id
 * @property string $session_id
 * @property string $launch_mode
 * @property string $fetch_token_hash
 * @property Carbon|null $fetch_used_at
 * @property string|null $auth_token_hash
 * @property Carbon $launched_at
 * @property Carbon|null $initialized_at
 * @property Carbon|null $terminated_at
 * @property Carbon|null $abandoned_at
 * @property Carbon|null $last_statement_at
 * @property Carbon $expires_at
 * @property-read LearningCmi5Registration|null $registration
 * @property-read LearningCmi5Unit|null $unit
 */
class LearningCmi5Session extends Model {
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'learning_cmi5_registration_id',
        'learning_cmi5_unit_id',
        'session_id',
        'launch_mode',
        'fetch_token_hash',
        'fetch_used_at',
        'auth_token_hash',
        'launched_at',
        'initialized_at',
        'terminated_at',
        'abandoned_at',
        'last_statement_at',
        'expires_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'fetch_used_at' => 'datetime',
        'launched_at' => 'datetime',
        'initialized_at' => 'datetime',
        'terminated_at' => 'datetime',
        'abandoned_at' => 'datetime',
        'last_statement_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /** @return BelongsTo<LearningCmi5Registration, $this> */
    public function registration(): BelongsTo {
        return $this->belongsTo(LearningCmi5Registration::class, 'learning_cmi5_registration_id');
    }

    /** @return BelongsTo<LearningCmi5Unit, $this> */
    public function unit(): BelongsTo {
        return $this->belongsTo(LearningCmi5Unit::class, 'learning_cmi5_unit_id');
    }

    public function hasEnded(): bool {
        return $this->terminated_at !== null || $this->abandoned_at !== null;
    }
}
