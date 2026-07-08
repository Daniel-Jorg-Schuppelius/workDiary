<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingEInvoice.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eingehende E-Rechnung im Prüfbereich (Feature 066, MVP-165/167):
 * Hash-Nachweis + Herkunft + Freigabe-Workflow; das unveränderte
 * Original liegt als Document im DMS.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $document_id
 * @property string $sha256
 * @property string $source
 * @property \Illuminate\Support\Carbon $received_at
 * @property string $status
 * @property int|null $decided_by
 * @property \Illuminate\Support\Carbon|null $decided_at
 * @property string|null $decision_note
 * @property array<string, mixed>|null $summary
 */
class IncomingEInvoice extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    /** Eloquent würde zu incoming_e_invoices pluralisieren. */
    protected $table = 'incoming_einvoices';

    public const STATUS_RECEIVED = 'received';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_QUESTION = 'question';

    public const STATUS_PAYMENT_RELEASED = 'payment_released';

    protected $fillable = [
        'organization_id', 'document_id', 'sha256', 'source', 'received_at',
        'status', 'decided_by', 'decided_at', 'decision_note', 'summary',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'received_at' => 'datetime',
        'decided_at' => 'datetime',
        'summary' => 'array',
    ];

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo {
        return $this->belongsTo(Document::class);
    }
}
