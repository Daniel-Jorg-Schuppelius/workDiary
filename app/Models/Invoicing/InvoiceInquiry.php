<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceInquiry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Invoicing;

use App\Enums\Invoicing\{InvoiceInquiryMatchKind, InvoiceInquiryStatus};
use App\Models\Communication\CommunicationNote;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Customer\Customer;
use App\Models\Platform\{Organization, User};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Nachfrage eines Kunden zu einer Rechnung, erkannt aus einer eingehenden
 * E-Mail (Feature 166, MVP-1199). Ziel ist entweder eine lokale Rechnung
 * (`invoice_id`) oder ein Beleg des Buchhaltungsprogramms
 * (`external_source` + `external_key`, Quelle = Plugin-ID). Eine Nachricht
 * ist je Organisation genau einmal erfasst (Unique auf `message_id`).
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $invoice_id
 * @property string|null $external_source
 * @property string|null $external_key
 * @property string $invoice_number
 * @property int|null $customer_id
 * @property string $message_id
 * @property string|null $in_reply_to
 * @property string $from_email
 * @property string|null $from_name
 * @property string|null $subject
 * @property Carbon $received_at
 * @property InvoiceInquiryMatchKind $match_kind
 * @property int|null $communication_note_id
 * @property InvoiceInquiryStatus $status
 * @property int|null $answered_by
 * @property Carbon|null $answered_at
 * @property-read Invoice|null $invoice
 * @property-read Customer|null $customer
 * @property-read CommunicationNote|null $communicationNote
 * @property-read User|null $answeredBy
 * @property-read Organization $organization
 */
class InvoiceInquiry extends Model {
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    /** @var list<string> */
    protected $fillable = [
        'organization_id',
        'invoice_id',
        'external_source',
        'external_key',
        'invoice_number',
        'customer_id',
        'message_id',
        'in_reply_to',
        'from_email',
        'from_name',
        'subject',
        'received_at',
        'match_kind',
        'communication_note_id',
        'status',
        'answered_by',
        'answered_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array {
        return [
            'received_at' => 'datetime',
            'answered_at' => 'datetime',
            'match_kind' => InvoiceInquiryMatchKind::class,
            'status' => InvoiceInquiryStatus::class,
        ];
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<CommunicationNote, $this> */
    public function communicationNote(): BelongsTo {
        return $this->belongsTo(CommunicationNote::class);
    }

    /** @return BelongsTo<User, $this> */
    public function answeredBy(): BelongsTo {
        return $this->belongsTo(User::class, 'answered_by');
    }

    /** Beleg eines Buchhaltungsprogramms statt lokaler Rechnung. */
    public function isExternal(): bool {
        return $this->invoice_id === null && $this->external_source !== null;
    }

    public function isOpen(): bool {
        return $this->status === InvoiceInquiryStatus::Open;
    }
}
