<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UserTerminalPin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Terminal-PIN einer Person (MVP-803) — nur der Hash, nie die PIN.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $user_id
 * @property string $pin_hash
 * @property int $failed_attempts
 * @property Carbon|null $locked_until
 * @property int|null $set_by
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 */
class UserTerminalPin extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $table = 'user_terminal_pins';

    protected $fillable = [
        'organization_id',
        'user_id',
        'pin_hash',
        'failed_attempts',
        'locked_until',
        'set_by',
    ];

    /** @var list<string> */
    protected $hidden = ['pin_hash'];

    /** @var array<string, string> */
    protected $casts = [
        'failed_attempts' => 'integer',
        'locked_until' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    public function isLocked(?Carbon $now = null): bool {
        return $this->locked_until !== null && $this->locked_until->isAfter($now ?? Carbon::now());
    }
}
