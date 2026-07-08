<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServiceTicketWatcher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ticket-Beobachter (Feature 065, MVP-151): zusätzliche Bearbeiter neben
 * assigned_to — erhalten Benachrichtigungen, keine Zuständigkeit.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $service_ticket_id
 * @property int $user_id
 */
class ServiceTicketWatcher extends Model {
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'service_ticket_id',
        'user_id',
    ];

    /** @return BelongsTo<ServiceTicket, $this> */
    public function ticket(): BelongsTo {
        return $this->belongsTo(ServiceTicket::class, 'service_ticket_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }
}
