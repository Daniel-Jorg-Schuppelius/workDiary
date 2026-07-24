<?php
/*
 * Created on   : Thu Jul 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerBillingAgreement.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Billing;

use App\Enums\Billing\BillingAgreementMode;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Customer;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * Sonder-Abrechnungsprofil eines Kunden (Feature 098): eigene Sätze je
 * Tätigkeit × Tagtyp und wahlweise rechnungsloses Kundenkonto mit laufendem
 * Saldo (mode=account) oder monatliche Rechnung (mode=invoice).
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int $customer_id
 * @property BillingAgreementMode $mode
 * @property CurrencyCode $currency
 * @property float|null $expected_monthly_amount
 * @property int $workdays_per_week
 * @property float $opening_balance
 * @property Carbon|null $opening_balance_date
 * @property bool $active
 * @property string|null $notes
 */
class CustomerBillingAgreement extends Model {
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected static function booted(): void {
        // Request-/Job-Cache des scoped Rate-Resolvers invalidieren, sonst
        // rechnet der laufende Request/Job mit veralteten Konditionen.
        static::saved(fn () => app(\App\Services\Billing\AgreementRateResolver::class)->flush());
        static::deleted(fn () => app(\App\Services\Billing\AgreementRateResolver::class)->flush());
    }

    protected $fillable = [
        'organization_id',
        'customer_id',
        'mode',
        'currency',
        'expected_monthly_amount',
        'workdays_per_week',
        'opening_balance',
        'opening_balance_date',
        'active',
        'notes',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'mode' => BillingAgreementMode::class,
        'currency' => CurrencyCode::class,
        'expected_monthly_amount' => 'decimal:2',
        'workdays_per_week' => 'integer',
        'opening_balance' => 'decimal:2',
        'opening_balance_date' => 'date',
        'active' => 'boolean',
    ];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasMany<CustomerBillingRate, $this> */
    public function rates(): HasMany {
        return $this->hasMany(CustomerBillingRate::class);
    }

    /** @return HasMany<CustomerBillingStatement, $this> */
    public function statements(): HasMany {
        return $this->hasMany(CustomerBillingStatement::class);
    }

    /** @return HasMany<CustomerAccountPayment, $this> */
    public function payments(): HasMany {
        return $this->hasMany(CustomerAccountPayment::class);
    }

    public function isAccountMode(): bool {
        return $this->active && $this->mode === BillingAgreementMode::Account;
    }

    public function isRetainerMode(): bool {
        return $this->active && $this->mode === BillingAgreementMode::Retainer;
    }

    /**
     * Führt einen laufenden Leistungssaldo (Konto-Kette): Account ODER Retainer.
     * Trennt die Saldo-Logik von isAccountMode() — im Retainer-Modus zahlt
     * Lexoffice (nicht die Bank an workDiary), daher bleibt das Bank-Matching
     * bewusst auf isAccountMode().
     */
    public function keepsLedger(): bool {
        return $this->active && in_array(
            $this->mode,
            [BillingAgreementMode::Account, BillingAgreementMode::Retainer],
            true,
        );
    }
}
