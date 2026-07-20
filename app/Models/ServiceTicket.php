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

use App\Enums\ServiceTicket\{ServiceTicketPriority, ServiceTicketSource, ServiceTicketStatus, SlaStatus};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasAttachments, HasSqid, Searchable};
use App\Services\ServiceTicket\SlaTimer;
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
 * @property int|null $diary_entry_id
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
    use HasAttachments;
    /** @use HasFactory<ServiceTicketFactory> */
    use HasFactory;

    use HasSqid;
    use Searchable;

    protected $fillable = [
        'organization_id',
        'queue_id',
        'kind',
        'ticket_no',
        'customer_id',
        'asset_id',
        'project_id',
        'diary_entry_id',
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
        'requester_type',
        'requester_id',
        'impact',
        'urgency',
        'wait_reason',
        'wait_until',
        'wait_owner_id',
        'escalation_level',
        'confidentiality',
        'resolution_summary',
        'close_code',
        'sla_snapshot',
        'is_major',
        'incident_lead_id',
        'stakeholders',
        'comm_rhythm',
        'workaround',
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
        'kind' => \App\Enums\ServiceTicket\ServiceTicketKind::class,
        'impact' => \App\Enums\ServiceTicket\TicketSeverity::class,
        'urgency' => \App\Enums\ServiceTicket\TicketSeverity::class,
        'close_code' => \App\Enums\ServiceTicket\TicketCloseCode::class,
        'wait_until' => 'datetime',
        'sla_snapshot' => 'array',
        'is_major' => 'boolean',
        'stakeholders' => 'array',
    ];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<ServiceQueue, $this> */
    public function queue(): BelongsTo {
        return $this->belongsTo(ServiceQueue::class, 'queue_id');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\MorphTo<\Illuminate\Database\Eloquent\Model, $this> */
    public function requester(): \Illuminate\Database\Eloquent\Relations\MorphTo {
        return $this->morphTo('requester');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<ServiceTicketWatcher, $this> */
    public function watchers(): \Illuminate\Database\Eloquent\Relations\HasMany {
        return $this->hasMany(ServiceTicketWatcher::class, 'service_ticket_id');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<ServiceTicketLink, $this> */
    public function links(): \Illuminate\Database\Eloquent\Relations\HasMany {
        return $this->hasMany(ServiceTicketLink::class, 'service_ticket_id');
    }

    /**
     * Probleme, hinter denen dieses Ticket als Incident hängt (MVP-156);
     * Gegenstück zu {@see Problem::tickets()}. Incidents schließen Probleme
     * NIE automatisch — die Relation ist rein informativ.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<Problem, $this>
     */
    public function problems(): \Illuminate\Database\Eloquent\Relations\BelongsToMany {
        return $this->belongsToMany(Problem::class, 'problem_ticket')->withTimestamps();
    }

    /**
     * Changes, an denen dieses Ticket hängt (MVP-157);
     * Gegenstück zu {@see Change::tickets()} — rein informativ.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<Change, $this>
     */
    public function changes(): \Illuminate\Database\Eloquent\Relations\BelongsToMany {
        return $this->belongsToMany(Change::class, 'change_ticket')->withTimestamps();
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<SlaClockSegment, $this> */
    public function slaClockSegments(): \Illuminate\Database\Eloquent\Relations\HasMany {
        return $this->hasMany(SlaClockSegment::class, 'service_ticket_id');
    }

    // Ticket-Anhänge (MVP-152) kommen aus HasAttachments (Vollaudit 2026-07,
    // N29); Nachrichten-Anhänge hängen an der jeweiligen ServiceTicketMessage.

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<DiaryEntry, $this> */
    public function diaryEntry(): BelongsTo {
        return $this->belongsTo(DiaryEntry::class);
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

    /**
     * Verantwortlich für die Wiedervorlage im Wartezustand (MVP-151).
     *
     * @return BelongsTo<User, $this>
     */
    public function waitOwner(): BelongsTo {
        return $this->belongsTo(User::class, 'wait_owner_id');
    }

    /**
     * Major-Incident-Leitung (MVP-155).
     *
     * @return BelongsTo<User, $this>
     */
    public function incidentLead(): BelongsTo {
        return $this->belongsTo(User::class, 'incident_lead_id');
    }

    /**
     * Abgeleiteter Lösungs-SLA-Status (reine Anzeige, Feature 010). Maßgeblich
     * ist die Lösungsfrist (resolution_due_at) über den {@see SlaTimer}.
     */
    public function slaStatus(): SlaStatus {
        return app(SlaTimer::class)->resolutionStatus($this);
    }

    /** Abgeleiteter Reaktions-SLA-Status (reine Anzeige). */
    public function slaReactionStatus(): SlaStatus {
        return app(SlaTimer::class)->reactionStatus($this);
    }

    /** Verbleibende Minuten bis zur Lösungsfrist (negativ = überfällig). */
    public function slaMinutesRemaining(): ?int {
        return app(SlaTimer::class)->minutesRemaining($this->resolution_due_at);
    }

    /** @return list<string> */
    protected function searchableColumns(): array {
        return ['ticket_no', 'title'];
    }
}
