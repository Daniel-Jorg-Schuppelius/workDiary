<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IcalSubscriptionItem.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Time;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Zuletzt gelesener Stand eines Termins (bzw. Serienvorkommens) einer
 * {@see IcalSubscription} (MVP-1516): Grundlage für den inkrementellen Lauf
 * und die Erkennung von Löschungen in der Quelle.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $ical_subscription_id
 * @property string $uid
 * @property string $uid_hash
 * @property int $sequence
 * @property Carbon|null $source_modified_at
 * @property string $fingerprint
 * @property Carbon|null $starts_at
 * @property Carbon|null $seen_at
 * @property Carbon|null $removed_at
 */
class IcalSubscriptionItem extends Model {
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'ical_subscription_id', 'uid', 'uid_hash', 'sequence',
        'source_modified_at', 'fingerprint', 'starts_at', 'seen_at', 'removed_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'sequence' => 'integer',
        'source_modified_at' => 'datetime',
        'starts_at' => 'datetime',
        'seen_at' => 'datetime',
        'removed_at' => 'datetime',
    ];

    /** @return BelongsTo<IcalSubscription, $this> */
    public function subscription(): BelongsTo {
        return $this->belongsTo(IcalSubscription::class, 'ical_subscription_id');
    }
}
