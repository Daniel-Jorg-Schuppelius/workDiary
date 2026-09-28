<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CrisisRoomPresence.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Crisis;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Platform\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Anwesenheit im Krisenraum (MVP-963): letzter Herzschlag je Person und Fall.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $crisis_case_id
 * @property int $user_id
 * @property Carbon $last_seen_at
 */
class CrisisRoomPresence extends Model {
    use BelongsToOrganization;

    public $timestamps = false;

    protected $fillable = ['organization_id', 'crisis_case_id', 'user_id', 'last_seen_at'];

    /** @var array<string, string> */
    protected $casts = ['last_seen_at' => 'datetime'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }
}
