<?php
/*
 * Created on   : Mon Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AppointmentRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Calendar;

use App\Enums\Calendar\AppointmentRequestStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Customer\Customer;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Models\Sales\BookableService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Quellenagnostischer Terminwunsch (Feature 095, minimaler 087-Intake-Kern):
 * ein extern gebuchter Termin (Quelle `calendly`) landet als `requested` und
 * wird ERST durch interne Bestätigung zum Dispositionseintrag
 * ({@see $diary_entry_id}) — zweiphasig. `source_uri` (Calendly-Invitee-URI)
 * ist der Idempotenz-Anker. Reschedule-Verlinkung über URI-Strings
 * (reihenfolge-unabhängig).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $source
 * @property string $source_uri
 * @property AppointmentRequestStatus $status
 * @property int|null $customer_id
 * @property int|null $portal_user_id
 * @property int|null $lead_id
 * @property int|null $bookable_service_id
 * @property int|null $assigned_user_id
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property string|null $decline_reason
 * @property Carbon|null $proposed_start_at
 * @property Carbon|null $proposed_end_at
 * @property string|null $proposal_note
 * @property int|null $proposer_user_id
 * @property int|null $diary_entry_id
 * @property Carbon|null $start_at
 * @property Carbon|null $end_at
 * @property string|null $invitee_timezone
 * @property string|null $invitee_name
 * @property string|null $invitee_email
 * @property string|null $service_label
 * @property string|null $location_type
 * @property string|null $location
 * @property string|null $join_url
 * @property string|null $cancel_url
 * @property string|null $reschedule_url
 * @property array<int, array<string, mixed>>|null $questions_and_answers
 * @property array<string, mixed>|null $tracking
 * @property array<string, mixed>|null $cancellation
 * @property bool $is_reschedule
 * @property string|null $rescheduled_from_uri
 * @property string|null $rescheduled_to_uri
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property string|null $decline_reason
 */
class AppointmentRequest extends Model implements \App\Services\Integration\Contracts\AcceptsInboxAssignment {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    public const string SOURCE_CALENDLY = 'calendly';

    public const string SOURCE_PORTAL = 'portal';

    protected $table = 'appointment_requests';

    protected $fillable = [
        'organization_id',
        'source',
        'source_uri',
        'status',
        'customer_id',
        'portal_user_id',
        'lead_id',
        'bookable_service_id',
        'assigned_user_id',
        'decided_by',
        'decided_at',
        'decline_reason',
        'diary_entry_id',
        'start_at',
        'end_at',
        'proposed_start_at',
        'proposed_end_at',
        'proposal_note',
        'proposer_user_id',
        'invitee_timezone',
        'invitee_name',
        'invitee_email',
        'service_label',
        'location_type',
        'location',
        'join_url',
        'cancel_url',
        'reschedule_url',
        'questions_and_answers',
        'tracking',
        'cancellation',
        'is_reschedule',
        'rescheduled_from_uri',
        'rescheduled_to_uri',
        'decided_by',
        'decided_at',
        'decline_reason',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'status' => AppointmentRequestStatus::class,
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'proposed_start_at' => 'datetime',
        'proposed_end_at' => 'datetime',
        'decided_at' => 'datetime',
        'is_reschedule' => 'boolean',
        'questions_and_answers' => 'array',
        'tracking' => 'array',
        'cancellation' => 'array',
    ];

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Sales\BookableService, $this> */
    public function bookableService(): \Illuminate\Database\Eloquent\Relations\BelongsTo {
        return $this->belongsTo(BookableService::class);
    }

    public function isPending(): bool {
        return $this->status === AppointmentRequestStatus::Requested;
    }

    /** Stornofrist der Leistungsart in Stunden vor Terminbeginn; ohne Angabe 24. */
    public function cancelHours(): int {
        return (int) ($this->bookableService->cancel_hours ?? 24);
    }

    /** Letzter Zeitpunkt, zu dem der Kunde im Portal selbst storniert. */
    public function cancelDeadline(): ?Carbon {
        return $this->start_at?->copy()->subHours($this->cancelHours());
    }

    public function withinCancelDeadline(): bool {
        $deadline = $this->cancelDeadline();

        return $deadline === null || Carbon::now()->lessThanOrEqualTo($deadline);
    }

    public function sourceLabel(): string {
        return match ($this->source) {
            self::SOURCE_PORTAL => __('Kundenportal'),
            self::SOURCE_CALENDLY => 'Calendly',
            default => __('Unbekannt'),
        };
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<\App\Models\Sales\Lead, $this> */
    public function lead(): BelongsTo {
        return $this->belongsTo(\App\Models\Sales\Lead::class);
    }

    /**
     * Kunde und/oder Lead festhalten (MVP-1610); ein konvertierter Lead bringt
     * seinen Kunden mit. Entschiedene Anfragen bleiben, wie sie entschieden wurden.
     */
    public function assignParty(?Customer $customer, ?\App\Models\Sales\Lead $lead): void {
        if (! $this->isAssignable()) {
            throw new \RuntimeException((string) __('appointment.assign.decided'));
        }
        $customerId = $customer->id ?? $lead?->customer_id;
        $this->forceFill(array_filter([
            'customer_id' => $customerId,
            'lead_id' => $lead?->id,
        ], static fn ($value): bool => $value !== null))->save();
        $this->audit('appointment.party_assigned', ['customer_id' => $customerId, 'lead_id' => $lead?->id]);
    }

    public function isAssignable(): bool {
        return in_array($this->status, [AppointmentRequestStatus::Requested, AppointmentRequestStatus::Proposed], true);
    }

    /** Zuordnung aus dem Integrations-Eingang (Calendly-Buchung ohne Kundentreffer); eine entschiedene Anfrage bleibt unberührt. */
    public function acceptInboxAssignment(Model $target): void {
        if (! $this->isAssignable()) {
            return;
        }
        if ($target instanceof Customer) {
            $this->assignParty($target, null);
        } elseif ($target instanceof \App\Models\Sales\Lead) {
            $this->assignParty(null, $target);
        }
    }

    /** @return BelongsTo<User, $this> */
    public function assignedUser(): BelongsTo {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /** @return BelongsTo<DiaryEntry, $this> */
    public function diaryEntry(): BelongsTo {
        return $this->belongsTo(DiaryEntry::class);
    }

    /** @return BelongsTo<User, $this> */
    public function decidedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
