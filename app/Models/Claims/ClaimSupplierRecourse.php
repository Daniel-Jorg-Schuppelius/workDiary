<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClaimSupplierRecourse.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Claims;

use App\Enums\Claims\ClaimRecourseStatus;
use App\Models\{Article, IncomingEInvoice, PurchaseOrder, Supplier};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lieferanten-/Herstellerregress (MVP-253): eigener Anspruch gegenüber
 * dem Vorlieferanten (Rügepflicht § 377 HGB gilt in Gegenrichtung) mit
 * Antwortfrist und Kostenrückfluss.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $claim_case_id
 * @property int $supplier_id
 * @property ClaimRecourseStatus $status
 * @property string|null $external_reference
 * @property string|null $amount_claimed
 * @property string|null $amount_recovered
 * @property \Illuminate\Support\Carbon|null $submitted_at
 * @property \Illuminate\Support\Carbon|null $response_due_at
 * @property \Illuminate\Support\Carbon|null $responded_at
 */
class ClaimSupplierRecourse extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'claim_case_id', 'supplier_id', 'purchase_order_id',
        'incoming_einvoice_id', 'article_id', 'serial_no', 'status',
        'external_reference', 'warranty_terms', 'amount_claimed',
        'amount_recovered', 'submitted_at', 'response_due_at', 'responded_at',
        'outcome_note', 'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'status' => ClaimRecourseStatus::class,
        'amount_claimed' => 'decimal:2',
        'amount_recovered' => 'decimal:2',
        'submitted_at' => 'datetime',
        'response_due_at' => 'datetime',
        'responded_at' => 'datetime',
    ];

    /** @return BelongsTo<ClaimCase, $this> */
    public function claimCase(): BelongsTo {
        return $this->belongsTo(ClaimCase::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<PurchaseOrder, $this> */
    public function purchaseOrder(): BelongsTo {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /** @return BelongsTo<IncomingEInvoice, $this> */
    public function incomingEInvoice(): BelongsTo {
        return $this->belongsTo(IncomingEInvoice::class, 'incoming_einvoice_id');
    }

    /** @return BelongsTo<Article, $this> */
    public function article(): BelongsTo {
        return $this->belongsTo(Article::class);
    }
}
