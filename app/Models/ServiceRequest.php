<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServiceRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphMany, MorphTo};

/**
 * Service-Request (Feature 065, MVP-154): 1:1 zum Ticket der Art
 * service_request; Formular + Katalogstand sind eingefroren
 * (Katalogänderung schreibt nie um).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $service_ticket_id
 * @property int $request_item_id
 * @property array<string, mixed>|null $form_snapshot
 * @property array<string, mixed> $catalog_snapshot
 * @property string $status
 * @property string|null $fulfilled_type
 * @property int|null $fulfilled_id
 */
class ServiceRequest extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING = 'pending_approval';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_FULFILLING = 'fulfilling';

    public const STATUS_DONE = 'done';

    protected $fillable = [
        'organization_id', 'service_ticket_id', 'request_item_id',
        'form_snapshot', 'catalog_snapshot', 'status', 'fulfilled_type', 'fulfilled_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'form_snapshot' => 'array',
        'catalog_snapshot' => 'array',
    ];

    /** @return BelongsTo<ServiceTicket, $this> */
    public function ticket(): BelongsTo {
        return $this->belongsTo(ServiceTicket::class, 'service_ticket_id');
    }

    /** @return BelongsTo<RequestItem, $this> */
    public function requestItem(): BelongsTo {
        return $this->belongsTo(RequestItem::class, 'request_item_id');
    }

    /** @return MorphMany<Approval, $this> */
    public function approvals(): MorphMany {
        return $this->morphMany(Approval::class, 'approvable')->orderBy('step');
    }

    /** @return MorphTo<Model, $this> */
    public function fulfilled(): MorphTo {
        return $this->morphTo('fulfilled');
    }
}
