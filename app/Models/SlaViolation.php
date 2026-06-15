<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SlaViolation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\ServiceTicket\SlaViolationKind;
use App\Models\Concerns\{Auditable, BelongsToOrganization};
use Database\Factories\SlaViolationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Verletzungsregister für SLA-Fristen (Feature 010). Jede Zeile dokumentiert
 * eine erkannte Überschreitung der Reaktions- oder Lösungsfrist eines
 * Service-Tickets — revisionssicher nachvollziehbar (Auditable), je Ticket+Typ
 * genau einmal (Unique-Key).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $service_ticket_id
 * @property int|null $sla_contract_id
 * @property SlaViolationKind $kind
 * @property Carbon|null $target_at
 * @property Carbon|null $breached_at
 * @property int $overdue_minutes
 * @property string|null $priority
 * @property string|null $cause
 * @property Carbon|null $acknowledged_at
 * @property int|null $acknowledged_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SlaViolation extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<SlaViolationFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'service_ticket_id',
        'sla_contract_id',
        'kind',
        'target_at',
        'breached_at',
        'overdue_minutes',
        'priority',
        'cause',
        'acknowledged_at',
        'acknowledged_by',
    ];

    protected $casts = [
        'kind' => SlaViolationKind::class,
        'target_at' => 'datetime',
        'breached_at' => 'datetime',
        'overdue_minutes' => 'integer',
        'acknowledged_at' => 'datetime',
    ];

    /** @return BelongsTo<ServiceTicket, $this> */
    public function serviceTicket(): BelongsTo {
        return $this->belongsTo(ServiceTicket::class);
    }

    /** @return BelongsTo<SlaContract, $this> */
    public function slaContract(): BelongsTo {
        return $this->belongsTo(SlaContract::class);
    }

    /** @return BelongsTo<User, $this> */
    public function acknowledgedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function isAcknowledged(): bool {
        return $this->acknowledged_at !== null;
    }
}
