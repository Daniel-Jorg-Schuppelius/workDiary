<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResalePlanLink.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Reselling;

use App\Enums\Reselling\LinkOrigin;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Platform\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Zuordnung Abo ↔ Position einer Serienvorlage (Feature 185, MVP-1411):
 * welche Vorlagenposition berechnet dieses Abo, mit wie vielen Lizenzen.
 * Herkunft wie bei Rechnungsbezügen: Vorschlag, bestätigt, manuell.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $subscription_id
 * @property string $plan_key
 * @property int $line_position
 * @property int $quantity
 * @property LinkOrigin $origin
 * @property string|null $note
 * @property int|null $created_by
 * @property CarbonImmutable|null $confirmed_at
 * @property-read ResaleSubscription $subscription
 */
class ResalePlanLink extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $table = 'resale_plan_links';

    protected $fillable = [
        'organization_id',
        'subscription_id',
        'plan_key',
        'line_position',
        'quantity',
        'origin',
        'note',
        'created_by',
        'confirmed_at',
    ];

    protected $casts = [
        'line_position' => 'integer',
        'quantity' => 'integer',
        'origin' => LinkOrigin::class,
        'confirmed_at' => 'immutable_datetime',
    ];

    /** @return BelongsTo<ResaleSubscription, $this> */
    public function subscription(): BelongsTo {
        return $this->belongsTo(ResaleSubscription::class, 'subscription_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Schlüssel der Vorlagenposition (`<plan_key>#<position>`). */
    public function lineKey(): string {
        return self::lineKeyOf($this->plan_key, $this->line_position);
    }

    public static function lineKeyOf(string $planKey, int $position): string {
        return $planKey . '#' . $position;
    }
}
