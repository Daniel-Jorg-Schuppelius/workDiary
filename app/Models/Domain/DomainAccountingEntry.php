<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainAccountingEntry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Domain;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Customer;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Read-only Accounting-Zeile aus `QueryAccountingList` (Feature 083, MVP-392).
 * Reine Projektion — WorkDiary erzeugt daraus KEINE steuerliche Rechnung und
 * kein Rechnungs-PDF. Dedup über `raw_hash`.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int $connection_id
 * @property string $external_user
 * @property string|null $accounting_id
 * @property int|null $reseller_account_id
 * @property int|null $domain_projection_id
 * @property int|null $customer_id
 * @property Carbon|null $entry_date
 * @property string|null $type
 * @property string|null $description
 * @property string|null $reference
 * @property float|string|null $quantity
 * @property float|string|null $net_amount
 * @property float|string|null $vat_rate
 * @property float|string|null $tax_amount
 * @property CurrencyCode|null $currency
 * @property string $raw_hash
 * @property Carbon|null $synced_at
 */
class DomainAccountingEntry extends Model {
    use BelongsToOrganization;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'connection_id',
        'external_user',
        'accounting_id',
        'reseller_account_id',
        'domain_projection_id',
        'customer_id',
        'entry_date',
        'type',
        'description',
        'reference',
        'quantity',
        'net_amount',
        'vat_rate',
        'tax_amount',
        'currency',
        'raw_hash',
        'synced_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'entry_date' => 'date',
        'quantity' => 'decimal:3',
        'net_amount' => 'decimal:2',
        'vat_rate' => 'decimal:3',
        'tax_amount' => 'decimal:2',
        'currency' => CurrencyCode::class,
        'synced_at' => 'datetime',
    ];

    /** @return BelongsTo<DomainProviderConnection, $this> */
    public function connection(): BelongsTo {
        return $this->belongsTo(DomainProviderConnection::class, 'connection_id');
    }

    /** @return BelongsTo<DomainResellerAccount, $this> */
    public function resellerAccount(): BelongsTo {
        return $this->belongsTo(DomainResellerAccount::class, 'reseller_account_id');
    }

    /** @return BelongsTo<DomainProjection, $this> */
    public function domainProjection(): BelongsTo {
        return $this->belongsTo(DomainProjection::class, 'domain_projection_id');
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
