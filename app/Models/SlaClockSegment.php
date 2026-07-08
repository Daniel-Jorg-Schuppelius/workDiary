<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SlaClockSegment.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SLA-Uhr-Pause (Feature 065, MVP-151): je Frist-Ziel (reaction/resolution)
 * ein Zeitfenster, in dem die Uhr steht — nur für Gründe, die der Vertrag
 * als pausierend deklariert (pause_rules). Reproduzierbare Fristen (DoD).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $service_ticket_id
 * @property string $target
 * @property \Illuminate\Support\Carbon $paused_from
 * @property \Illuminate\Support\Carbon|null $paused_to
 * @property string $reason
 */
class SlaClockSegment extends Model {
    use BelongsToOrganization;

    public const TARGET_REACTION = 'reaction';

    public const TARGET_RESOLUTION = 'resolution';

    protected $fillable = [
        'organization_id',
        'service_ticket_id',
        'target',
        'paused_from',
        'paused_to',
        'reason',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'paused_from' => 'datetime',
        'paused_to' => 'datetime',
    ];

    /** @return BelongsTo<ServiceTicket, $this> */
    public function ticket(): BelongsTo {
        return $this->belongsTo(ServiceTicket::class, 'service_ticket_id');
    }
}
