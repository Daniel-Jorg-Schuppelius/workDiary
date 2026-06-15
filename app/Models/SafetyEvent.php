<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SafetyEvent.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Safety\{SafetyEventKind, SafetyEventSeverity, SafetyEventStatus};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasAttachments, HasSqid};
use Database\Factories\SafetyEventFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphMany, MorphTo};
use Illuminate\Support\Carbon;

/**
 * Sicherheitsereignis (Feature 013): Unfall, Beinaheunfall, Gefährdung oder
 * Mangel mit Schweregrad, Sofortmaßnahme, Ursachenanalyse und Statusmaschine
 * im SafetyEventService. event_no läuft je Organisation. Foto-Nachweise über
 * HasAttachments.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $event_no
 * @property SafetyEventKind $kind
 * @property SafetyEventSeverity $severity
 * @property Carbon $occurred_at
 * @property string|null $location
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property int $reported_by_user_id
 * @property string|null $affected_person
 * @property string $description
 * @property string|null $immediate_action
 * @property SafetyEventStatus $status
 * @property string|null $root_cause
 * @property Carbon|null $closed_at
 * @property int|null $closed_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SafetyEvent extends Model {
    use Auditable;

    use BelongsToOrganization;
    use HasAttachments;
    /** @use HasFactory<SafetyEventFactory> */
    use HasFactory;
    use HasSqid;

    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'event_no',
        'kind',
        'severity',
        'occurred_at',
        'location',
        'subject_type',
        'subject_id',
        'reported_by_user_id',
        'affected_person',
        'description',
        'immediate_action',
        'status',
        'root_cause',
        'closed_at',
        'closed_by_user_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'event_no' => 'integer',
        'kind' => SafetyEventKind::class,
        'severity' => SafetyEventSeverity::class,
        'status' => SafetyEventStatus::class,
        'occurred_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    /** Anzeige-Kennung im Register (z. B. "SE-12"). */
    public function displayNo(): string {
        return 'SE-' . $this->event_no;
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function closer(): BelongsTo {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    /**
     * Folgemaßnahmen als offene Punkte (createFollowUpIssue setzt den
     * SafetyEvent als Subject des OpenIssue).
     *
     * @return MorphMany<OpenIssue, $this>
     */
    public function openIssues(): MorphMany {
        return $this->morphMany(OpenIssue::class, 'subject');
    }

    /**
     * Offene Ereignisse (alles außer geschlossen).
     *
     * @param  Builder<SafetyEvent>  $query
     * @return Builder<SafetyEvent>
     */
    public function scopeOpen(Builder $query): Builder {
        return $query->where('status', '!=', SafetyEventStatus::Closed->value);
    }
}
