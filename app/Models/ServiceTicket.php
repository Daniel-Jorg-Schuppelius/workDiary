<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServiceTicket.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\ServiceTicket\{ServiceTicketPriority, ServiceTicketSource, ServiceTicketStatus};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Database\Factories\ServiceTicketFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $ticket_no
 * @property int|null $customer_id
 * @property int|null $asset_id
 * @property int|null $project_id
 * @property int|null $sla_contract_id
 * @property string $title
 * @property string|null $description
 * @property ServiceTicketStatus $status
 * @property ServiceTicketPriority $priority
 * @property ServiceTicketSource $source
 * @property string|null $source_reference
 * @property int|null $reported_by_user_id
 * @property int|null $assigned_to_user_id
 * @property Carbon|null $reported_at
 * @property Carbon|null $acknowledged_at
 * @property Carbon|null $scheduled_for
 * @property Carbon|null $started_at
 * @property Carbon|null $resolved_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $reaction_due_at
 * @property Carbon|null $resolution_due_at
 * @property bool $reaction_breached
 * @property bool $resolution_breached
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ServiceTicket extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<ServiceTicketFactory> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'ticket_no',
        'customer_id',
        'asset_id',
        'project_id',
        'sla_contract_id',
        'title',
        'description',
        'status',
        'priority',
        'source',
        'source_reference',
        'reported_by_user_id',
        'assigned_to_user_id',
        'reported_at',
        'acknowledged_at',
        'scheduled_for',
        'started_at',
        'resolved_at',
        'accepted_at',
        'closed_at',
        'reaction_due_at',
        'resolution_due_at',
        'reaction_breached',
        'resolution_breached',
    ];

    protected $casts = [
        'status' => ServiceTicketStatus::class,
        'priority' => ServiceTicketPriority::class,
        'source' => ServiceTicketSource::class,
        'reported_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'scheduled_for' => 'datetime',
        'started_at' => 'datetime',
        'resolved_at' => 'datetime',
        'accepted_at' => 'datetime',
        'closed_at' => 'datetime',
        'reaction_due_at' => 'datetime',
        'resolution_due_at' => 'datetime',
        'reaction_breached' => 'bool',
        'resolution_breached' => 'bool',
    ];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<SlaContract, $this> */
    public function slaContract(): BelongsTo {
        return $this->belongsTo(SlaContract::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reportedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignedTo(): BelongsTo {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }
}
