<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResalePurchaseEntry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Reselling;

use App\Casts\MoneyCast;
use App\Enums\Reselling\SubscriptionProvider;
use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use App\Models\Domain\DomainAccountingEntry;
use App\Models\{LexofficeVoucher, Organization, User};
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Einkaufsbeleg-Zeile (Feature 152, MVP-762): Ist-Einkauf einer Periode
 * aus Eingangsrechnung, Domain-Buchung oder Handeingabe.
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $subscription_id
 * @property int|null $period_id
 * @property SubscriptionProvider $provider
 * @property string $source
 * @property int|null $lexoffice_voucher_id
 * @property int|null $domain_accounting_entry_id
 * @property string|null $document_number
 * @property CarbonImmutable $entry_date
 * @property string|null $description
 * @property Money $net_amount
 * @property CurrencyCode $currency
 * @property string $raw_hash
 * @property int|null $created_by_user_id
 */
class ResalePurchaseEntry extends Model {
    use BelongsToOrganization;
    use HasSqid;

    public const SOURCE_VOUCHER = 'voucher';
    public const SOURCE_DOMAIN = 'domain_accounting';
    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_PROVIDER_INVOICE = 'provider_invoice';

    protected $table = 'resale_purchase_entries';

    protected $fillable = [
        'organization_id',
        'subscription_id',
        'period_id',
        'provider',
        'source',
        'lexoffice_voucher_id',
        'domain_accounting_entry_id',
        'document_number',
        'entry_date',
        'description',
        'net_amount',
        'currency',
        'raw_hash',
        'created_by_user_id',
    ];

    protected $casts = [
        'provider' => SubscriptionProvider::class,
        'entry_date' => 'immutable_date',
        'currency' => CurrencyCode::class,
        'net_amount' => MoneyCast::class . ':currency,2',
    ];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<ResaleSubscription, $this> */
    public function subscription(): BelongsTo {
        return $this->belongsTo(ResaleSubscription::class, 'subscription_id');
    }

    /** @return BelongsTo<ResalePeriod, $this> */
    public function period(): BelongsTo {
        return $this->belongsTo(ResalePeriod::class, 'period_id');
    }

    /** @return BelongsTo<LexofficeVoucher, $this> */
    public function voucher(): BelongsTo {
        return $this->belongsTo(LexofficeVoucher::class, 'lexoffice_voucher_id');
    }

    /** @return BelongsTo<DomainAccountingEntry, $this> */
    public function domainAccountingEntry(): BelongsTo {
        return $this->belongsTo(DomainAccountingEntry::class, 'domain_accounting_entry_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function sourceLabel(): string {
        return (string) __('resale.purchase.source.' . $this->source);
    }
}
