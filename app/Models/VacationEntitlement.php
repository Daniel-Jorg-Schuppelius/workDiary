<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VacationEntitlement.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jahresanspruch + Übertrag je Nutzer (MVP-413, Urlaubskonto).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $user_id
 * @property int $year
 * @property float $entitled_days
 * @property float $carryover_days
 * @property Carbon|null $carryover_expires_on
 * @property string|null $note
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class VacationEntitlement extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'user_id',
        'year',
        'entitled_days',
        'severely_disabled_days',
        'other_days',
        'carryover_days',
        'carryover_expires_on',
        'note',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'year' => 'integer',
        'entitled_days' => 'float',
        'carryover_days' => 'float',
        'carryover_expires_on' => 'date',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }
}
