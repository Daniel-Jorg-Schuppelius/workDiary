<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServiceTicketLink.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};

/**
 * Ticketverknüpfung (Feature 065, MVP-155): related/duplicate/parent
 * zwischen Tickets sowie security/privacy als reine Morph-Verknüpfung auf
 * ISMS-/Datenschutz-Objekte (nie Konvertierung).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $service_ticket_id
 * @property string $linked_type
 * @property int $linked_id
 * @property string $kind
 */
class ServiceTicketLink extends Model {
    use BelongsToOrganization;

    public const KINDS = ['related', 'duplicate', 'parent', 'security', 'privacy'];

    protected $fillable = [
        'organization_id',
        'service_ticket_id',
        'linked_type',
        'linked_id',
        'kind',
    ];

    /** @return BelongsTo<ServiceTicket, $this> */
    public function ticket(): BelongsTo {
        return $this->belongsTo(ServiceTicket::class, 'service_ticket_id');
    }

    /** @return MorphTo<Model, $this> */
    public function linked(): MorphTo {
        return $this->morphTo('linked');
    }
}
