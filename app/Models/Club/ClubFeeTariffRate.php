<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubFeeTariffRate.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Club;

use App\Casts\MoneyCast;
use App\Enums\Club\ClubFeeProration;
use App\Enums\Finance\RecurringInterval;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Tarifsatz je Gültigkeitsdatum (MVP-849): Rhythmus, Betrag, Abrechnungsanker,
 * Fälligkeit, Anteilsregel und optionale Aufnahmegebühr. Freigegebene
 * Forderungen ändern sich durch neue Sätze nicht (MVP-850).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $club_fee_tariff_id
 * @property Carbon $valid_from
 * @property RecurringInterval $interval
 * @property Money $amount
 * @property CurrencyCode $currency
 * @property int $anchor_month
 * @property int $due_days
 * @property ClubFeeProration $proration
 * @property Money|null $admission_fee
 */
class ClubFeeTariffRate extends Model {
    use Auditable;

    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = ['organization_id', 'club_fee_tariff_id', 'valid_from', 'interval', 'amount', 'currency', 'anchor_month', 'due_days', 'proration', 'admission_fee'];

    protected $casts = [
        'valid_from' => 'date',
        'interval' => RecurringInterval::class,
        'amount' => MoneyCast::class . ':currency',
        'currency' => CurrencyCode::class,
        'anchor_month' => 'integer',
        'due_days' => 'integer',
        'proration' => ClubFeeProration::class,
        'admission_fee' => MoneyCast::class . ':currency',
    ];

    /** @return BelongsTo<ClubFeeTariff, $this> */
    public function tariff(): BelongsTo {
        return $this->belongsTo(ClubFeeTariff::class, 'club_fee_tariff_id');
    }
}
