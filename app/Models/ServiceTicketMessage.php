<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServiceTicketMessage.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\ServiceTicket\TicketMessageKind;
use App\Models\Concerns\{BelongsToOrganization, HasAttachments, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};

/**
 * Ticket-Nachricht (Feature 065, MVP-152). Sichtbarkeit leitet sich aus
 * kind ab (public_reply = kundensichtbar) — nie aus einem Flag.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $service_ticket_id
 * @property TicketMessageKind $kind
 * @property string|null $author_type
 * @property int|null $author_id
 * @property array<int, string>|null $to
 * @property array<int, string>|null $cc
 * @property string|null $subject
 * @property string $body
 * @property string $channel
 * @property string|null $message_id
 * @property string|null $in_reply_to
 * @property string|null $delivery_status
 */
class ServiceTicketMessage extends Model {
    use BelongsToOrganization;
    use HasAttachments;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'service_ticket_id',
        'kind',
        'author_type',
        'author_id',
        'to',
        'cc',
        'subject',
        'body',
        'channel',
        'message_id',
        'in_reply_to',
        'delivery_status',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'kind' => TicketMessageKind::class,
        'to' => 'array',
        'cc' => 'array',
    ];

    /** @return BelongsTo<ServiceTicket, $this> */
    public function ticket(): BelongsTo {
        return $this->belongsTo(ServiceTicket::class, 'service_ticket_id');
    }

    /** @return MorphTo<\Illuminate\Database\Eloquent\Model, $this> */
    public function author(): MorphTo {
        return $this->morphTo('author');
    }

    // Anhänge (MVP-152) kommen aus HasAttachments (Vollaudit 2026-07, N29);
    // Kundensichtbarkeit regelt das `customer_visible`-Flag je Anhang.
}
