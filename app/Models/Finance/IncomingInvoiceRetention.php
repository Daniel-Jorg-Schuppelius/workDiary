<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingInvoiceRetention.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Finance;

use App\Enums\Invoicing\{RetentionKind, RetentionStatus};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Invoicing\IncomingEInvoice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Einbehalt an einer Eingangsrechnung (MVP-953), Spiegel zu
 * {@see \App\Models\Invoicing\InvoiceRetention}: der Zahllauf zahlt nur den
 * Rest, nach der Freigabe wird der Einbehalt ein eigener Zahlposten.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $incoming_einvoice_id
 * @property RetentionKind $kind
 * @property numeric-string|null $percent
 * @property numeric-string $amount
 * @property string $currency
 * @property Carbon|null $due_on
 * @property RetentionStatus $status
 * @property Carbon|null $released_on
 * @property int|null $releaser_user_id
 * @property int|null $paid_in_run_id
 * @property string|null $note
 * @property int|null $created_by
 */
class IncomingInvoiceRetention extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'incoming_einvoice_id', 'kind', 'percent', 'amount', 'currency', 'due_on', 'status',
        'released_on', 'releaser_user_id', 'paid_in_run_id', 'note', 'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'kind' => RetentionKind::class,
        'status' => RetentionStatus::class,
        'percent' => 'decimal:2',
        'amount' => 'decimal:2',
        'due_on' => 'date',
        'released_on' => 'date',
    ];

    /** @return BelongsTo<IncomingEInvoice, $this> */
    public function incomingEInvoice(): BelongsTo {
        return $this->belongsTo(IncomingEInvoice::class, 'incoming_einvoice_id');
    }

    public function isOverdue(): bool {
        return $this->status === RetentionStatus::Open && $this->due_on !== null && $this->due_on->endOfDay()->isPast();
    }
}
