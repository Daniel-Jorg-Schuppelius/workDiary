<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TicketSatisfaction.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Zufriedenheits-Kurzbewertung (Feature 065, MVP-159/160): genau EINE
 * Antwort je Ticket (DB-Unique), nur über das Kundenportal nach Abschluss.
 * Tabellenname ist bewusst singular (`ticket_satisfaction`) — daher
 * explizites `$table`.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $service_ticket_id
 * @property int|null $portal_user_id
 * @property int $score
 * @property string|null $comment
 * @property \Illuminate\Support\Carbon $answered_at
 */
class TicketSatisfaction extends Model {
    use BelongsToOrganization;

    protected $table = 'ticket_satisfaction';

    protected $fillable = [
        'organization_id',
        'service_ticket_id',
        'portal_user_id',
        'score',
        'comment',
        'answered_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'score' => 'integer',
        'answered_at' => 'datetime',
    ];

    /** @return BelongsTo<ServiceTicket, $this> */
    public function ticket(): BelongsTo {
        return $this->belongsTo(ServiceTicket::class, 'service_ticket_id');
    }

    /** @return BelongsTo<User, $this> */
    public function portalUser(): BelongsTo {
        return $this->belongsTo(User::class, 'portal_user_id');
    }
}
