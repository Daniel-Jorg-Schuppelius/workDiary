<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalRateItem.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Rental;

use App\Enums\Rental\RentalChargeKind;
use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Einzelkondition einer Rate Card (Tagessatz, Zuschlag, Pauschale, …).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $rental_rate_card_id
 * @property RentalChargeKind $kind
 * @property string $label
 * @property string|null $group_code
 * @property numeric-string $amount
 * @property string $unit
 * @property int|null $min_duration_days
 */
class RentalRateItem extends Model {
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'rental_rate_card_id', 'kind', 'label',
        'group_code', 'amount', 'unit', 'min_duration_days', 'note',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'kind' => RentalChargeKind::class,
        'amount' => 'decimal:2',
        'min_duration_days' => 'integer',
    ];

    /** @return BelongsTo<RentalRateCard, $this> */
    public function rateCard(): BelongsTo {
        return $this->belongsTo(RentalRateCard::class, 'rental_rate_card_id');
    }
}
