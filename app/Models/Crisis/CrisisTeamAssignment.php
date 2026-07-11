<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CrisisTeamAssignment.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Crisis;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stabsbesetzung (Feature 070, MVP-213): Rolle + Person + Stellvertretung,
 * Alarmierungs- und Quittierungsstatus (D7).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $crisis_case_id
 * @property int $crisis_role_id
 * @property int $user_id
 * @property int|null $deputy_user_id
 * @property string|null $contact_note
 * @property \Illuminate\Support\Carbon|null $alerted_at
 * @property \Illuminate\Support\Carbon|null $acknowledged_at
 * @property \Illuminate\Support\Carbon|null $deputy_alerted_at
 */
class CrisisTeamAssignment extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'crisis_case_id', 'crisis_role_id', 'user_id',
        'deputy_user_id', 'contact_note', 'alerted_at', 'acknowledged_at',
        'deputy_alerted_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'alerted_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'deputy_alerted_at' => 'datetime',
    ];

    /** @return BelongsTo<CrisisCase, $this> */
    public function crisisCase(): BelongsTo {
        return $this->belongsTo(CrisisCase::class, 'crisis_case_id');
    }

    /** @return BelongsTo<CrisisRole, $this> */
    public function role(): BelongsTo {
        return $this->belongsTo(CrisisRole::class, 'crisis_role_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function deputy(): BelongsTo {
        return $this->belongsTo(User::class, 'deputy_user_id');
    }
}
