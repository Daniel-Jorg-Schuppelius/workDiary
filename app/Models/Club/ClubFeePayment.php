<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubFeePayment.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Club;

use App\Casts\MoneyCast;
use App\Enums\Club\{ClubFeePaymentMethod, ClubFeePaymentSource};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Finance\{BankTransaction, PaymentAllocation, PaymentRunItem};
use App\Models\User;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Beitragszahlung (MVP-851): Geld eines Kontos, einer Forderung zugeordnet
 * oder als Guthaben (ohne Forderung); negativ bei Rücklastschrift oder
 * Guthabenverrechnung. Dasselbe Geld wird nie zweimal angerechnet — der
 * Bankabgleich erkennt eine manuell oder per Einzug gebuchte Zahlung wieder.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $club_fee_account_id
 * @property int|null $club_fee_claim_id
 * @property Money $amount
 * @property CurrencyCode $currency
 * @property Carbon $paid_on
 * @property ClubFeePaymentMethod $method
 * @property ClubFeePaymentSource $source
 * @property string|null $reference
 * @property string|null $note
 * @property int|null $bank_transaction_id
 * @property int|null $payment_allocation_id
 * @property int|null $payment_run_item_id
 * @property int|null $chargeback_of_id
 * @property int|null $created_by_user_id
 */
class ClubFeePayment extends Model {
    use Auditable;

    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'club_fee_account_id', 'club_fee_claim_id', 'amount', 'currency', 'paid_on', 'method', 'source', 'reference', 'note',
        'bank_transaction_id', 'payment_allocation_id', 'payment_run_item_id', 'chargeback_of_id', 'created_by_user_id',
    ];

    protected $casts = [
        'amount' => MoneyCast::class . ':currency',
        'currency' => CurrencyCode::class,
        'paid_on' => 'date',
        'method' => ClubFeePaymentMethod::class,
        'source' => ClubFeePaymentSource::class,
    ];

    /** @return BelongsTo<ClubFeeAccount, $this> */
    public function account(): BelongsTo {
        return $this->belongsTo(ClubFeeAccount::class, 'club_fee_account_id');
    }

    /** @return BelongsTo<ClubFeeClaim, $this> */
    public function claim(): BelongsTo {
        return $this->belongsTo(ClubFeeClaim::class, 'club_fee_claim_id');
    }

    /** @return BelongsTo<BankTransaction, $this> */
    public function bankTransaction(): BelongsTo {
        return $this->belongsTo(BankTransaction::class);
    }

    /** @return BelongsTo<PaymentAllocation, $this> */
    public function allocation(): BelongsTo {
        return $this->belongsTo(PaymentAllocation::class, 'payment_allocation_id');
    }

    /** @return BelongsTo<PaymentRunItem, $this> */
    public function runItem(): BelongsTo {
        return $this->belongsTo(PaymentRunItem::class, 'payment_run_item_id');
    }

    /** @return BelongsTo<ClubFeePayment, $this> */
    public function chargebackOf(): BelongsTo {
        return $this->belongsTo(self::class, 'chargeback_of_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function isChargeback(): bool {
        return $this->source === ClubFeePaymentSource::Chargeback;
    }

    public function isCredit(): bool {
        return $this->club_fee_claim_id === null;
    }
}
