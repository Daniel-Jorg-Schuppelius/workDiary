<?php
/*
 * Created on   : Thu Jun 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OnboardingProgress.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $step_code
 * @property string $state
 * @property \Illuminate\Support\Carbon|null $done_at
 * @property int|null $done_by_user_id
 * @property string|null $skipped_reason
 */
class OnboardingProgress extends Model {
    use BelongsToOrganization;

    protected $table = 'onboarding_progress';

    protected $fillable = [
        'organization_id',
        'step_code',
        'state',
        'done_at',
        'done_by_user_id',
        'skipped_reason',
    ];

    protected $casts = [
        'done_at' => 'datetime',
    ];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<User, $this> */
    public function doneBy(): BelongsTo {
        return $this->belongsTo(User::class, 'done_by_user_id');
    }
}
