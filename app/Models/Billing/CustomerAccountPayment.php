<?php
/*
 * Created on   : Thu Jul 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerAccountPayment.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Billing;

use App\Enums\Billing\AccountPaymentSource;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Finance\{BankTransaction, PaymentAllocation};
use App\Models\User;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Zahlung auf ein Kundenkonto (Feature 098, Excel-Spalte „Abgerechnet").
 * Bank-gematchte Zahlungen tragen bank_transaction_id + payment_allocation_id
 * (Rückreferenz für unmatch); Rückläufer werden als Negativzeile gebucht.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int $customer_billing_agreement_id
 * @property Carbon $paid_on
 * @property float $amount
 * @property CurrencyCode $currency
 * @property AccountPaymentSource $source
 * @property string|null $source_reference
 * @property int|null $bank_transaction_id
 * @property int|null $payment_allocation_id
 * @property string|null $note
 * @property int|null $created_by_user_id
 */
class CustomerAccountPayment extends Model {
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'customer_billing_agreement_id',
        'paid_on',
        'amount',
        'currency',
        'source',
        'source_reference',
        'bank_transaction_id',
        'payment_allocation_id',
        'note',
        'created_by_user_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'paid_on' => 'date',
        'amount' => 'decimal:2',
        'currency' => CurrencyCode::class,
        'source' => AccountPaymentSource::class,
    ];

    /** @return BelongsTo<CustomerBillingAgreement, $this> */
    public function agreement(): BelongsTo {
        return $this->belongsTo(CustomerBillingAgreement::class, 'customer_billing_agreement_id');
    }

    /** @return BelongsTo<BankTransaction, $this> */
    public function bankTransaction(): BelongsTo {
        return $this->belongsTo(BankTransaction::class);
    }

    /** @return BelongsTo<PaymentAllocation, $this> */
    public function paymentAllocation(): BelongsTo {
        return $this->belongsTo(PaymentAllocation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
