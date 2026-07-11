<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClaimFinancialOutcome.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Claims;

use App\Enums\Claims\{ClaimFinancialKind, ClaimFinancialStatus};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\{Invoice, User};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kaufmännische Folge (MVP-252, D1): Art lebt hier (price_reduction/
 * credit_note/cancellation/correction/replacement_invoice/refund);
 * Vier-Augen-Freigabe vor Ausführung, Faktura-Folgebeleg als Verweis.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $claim_case_id
 * @property ClaimFinancialKind $kind
 * @property ClaimFinancialStatus $status
 * @property string|null $amount
 * @property \CommonToolkit\Enums\CurrencyCode $currency
 * @property int|null $invoice_id
 * @property int|null $result_invoice_id
 * @property string|null $external_reference
 * @property string $justification
 * @property int $proposed_by
 * @property int|null $approved_by
 * @property \Illuminate\Support\Carbon|null $approved_at
 * @property \Illuminate\Support\Carbon|null $executed_at
 */
class ClaimFinancialOutcome extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'claim_case_id', 'kind', 'status', 'amount',
        'currency', 'invoice_id', 'result_invoice_id', 'external_reference', 'justification',
        'proposed_by', 'approved_by', 'approved_at', 'executed_at', 'note',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'currency' => \CommonToolkit\Enums\CurrencyCode::class,
        'kind' => ClaimFinancialKind::class,
        'status' => ClaimFinancialStatus::class,
        'amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'executed_at' => 'datetime',
    ];

    /** @return BelongsTo<ClaimCase, $this> */
    public function claimCase(): BelongsTo {
        return $this->belongsTo(ClaimCase::class);
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<Invoice, $this> */
    public function resultInvoice(): BelongsTo {
        return $this->belongsTo(Invoice::class, 'result_invoice_id');
    }

    /** @return BelongsTo<User, $this> */
    public function proposer(): BelongsTo {
        return $this->belongsTo(User::class, 'proposed_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
