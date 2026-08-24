<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PaymentRunItem.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Finance;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\{Customer, IncomingEInvoice, Supplier};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Einzelposition eines Zahllaufs (Feature 120, MVP-609).
 *
 * `gross_amount` bleibt neben `amount` stehen: Skontoabzug und Kürzung sollen
 * belegbar sein, nicht aus der Differenz erschlossen werden müssen.
 *
 * @property string|null $iban
 * @property string|null $bic
 */
class PaymentRunItem extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<\Database\Factories\Finance\PaymentRunItemFactory> */
    use HasFactory;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'payment_run_id', 'incoming_einvoice_id',
        'supplier_id', 'customer_id', 'sepa_mandate_id',
        'party_name', 'iban', 'bic', 'amount', 'gross_amount',
        'discount_percent', 'deduction_reason', 'reference', 'end_to_end_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'iban' => 'encrypted',
        'bic' => 'encrypted',
        'amount' => 'decimal:2',
        'gross_amount' => 'decimal:2',
        'discount_percent' => 'decimal:2',
    ];

    /** @return BelongsTo<PaymentRun, $this> */
    public function run(): BelongsTo {
        return $this->belongsTo(PaymentRun::class, 'payment_run_id');
    }

    /** @return BelongsTo<IncomingEInvoice, $this> */
    public function incomingEInvoice(): BelongsTo {
        return $this->belongsTo(IncomingEInvoice::class, 'incoming_einvoice_id');
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<SepaMandate, $this> */
    public function mandate(): BelongsTo {
        return $this->belongsTo(SepaMandate::class, 'sepa_mandate_id');
    }

    /** Wurde weniger gezahlt als berechnet? (Skonto oder Kürzung) */
    public function isReduced(): bool {
        return $this->gross_amount !== null && (float) $this->gross_amount > (float) $this->amount;
    }
}
