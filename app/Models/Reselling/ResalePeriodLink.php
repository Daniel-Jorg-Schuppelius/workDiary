<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResalePeriodLink.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Reselling;

use App\Casts\MoneyCast;
use App\Enums\Reselling\LinkOrigin;
use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use App\Models\{InvoiceItem, LexofficeVoucher, LexofficeVoucherLine, User};
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};

/**
 * Rechnungsbezug einer Periode (Feature 152, MVP-761): Belegposition
 * (`LexofficeVoucherLine`, `InvoiceItem`) oder Belegkopf (`LexofficeVoucher`)
 * mit gedeckten Lizenzmonaten. Eine Position kann mehrere Perioden decken
 * (Mehrjahresblock), eine Periode mehrere Positionen.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $period_id
 * @property int $subscription_id
 * @property string $linkable_type
 * @property int $linkable_id
 * @property string|null $voucher_number
 * @property CarbonImmutable|null $voucher_date
 * @property string $quantity
 * @property string $months
 * @property Money|null $amount
 * @property CurrencyCode $currency
 * @property LinkOrigin $origin
 * @property string|null $note
 * @property int|null $created_by_user_id
 * @property CarbonImmutable|null $confirmed_at
 * @property-read ResalePeriod $period
 * @property-read Model|null $linkable
 */
class ResalePeriodLink extends Model {
    use BelongsToOrganization;
    use HasSqid;

    protected $table = 'resale_period_links';

    protected $fillable = [
        'organization_id',
        'period_id',
        'subscription_id',
        'linkable_type',
        'linkable_id',
        'voucher_number',
        'voucher_date',
        'quantity',
        'months',
        'amount',
        'currency',
        'origin',
        'note',
        'created_by_user_id',
        'confirmed_at',
    ];

    protected $casts = [
        'voucher_date' => 'immutable_date',
        'quantity' => 'decimal:3',
        'months' => 'decimal:2',
        'currency' => CurrencyCode::class,
        'amount' => MoneyCast::class . ':currency,2',
        'origin' => LinkOrigin::class,
        'confirmed_at' => 'immutable_datetime',
    ];

    /** @return BelongsTo<ResalePeriod, $this> */
    public function period(): BelongsTo {
        return $this->belongsTo(ResalePeriod::class, 'period_id');
    }

    /** @return BelongsTo<ResaleSubscription, $this> */
    public function subscription(): BelongsTo {
        return $this->belongsTo(ResaleSubscription::class, 'subscription_id');
    }

    /** @return MorphTo<Model, $this> */
    public function linkable(): MorphTo {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** Anzeigetext der verknüpften Position. */
    public function lineLabel(): string {
        $linkable = $this->linkable;
        if ($linkable instanceof LexofficeVoucherLine) {
            return $linkable->name;
        }
        if ($linkable instanceof InvoiceItem) {
            return (string) $linkable->description;
        }
        if ($linkable instanceof LexofficeVoucher) {
            return (string) __('resale.link.voucher_only');
        }

        return '';
    }
}
