<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsSecurityIncident.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Isms;

use App\Enums\Isms\{IncidentSeverity, SecurityIncidentCategory, SecurityIncidentStatus};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Database\Factories\Isms\IsmsSecurityIncidentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany};
use Illuminate\Support\Carbon;

/**
 * Informationssicherheitsvorfall (Feature 044, MVP 2): UNABHÄNGIG vom
 * Personenbezug erfasster Vorfall mit Bewertung, Eindämmung, Ursachenanalyse,
 * Kommunikation (impact) und Lessons Learned; Statusmaschine im
 * SecurityIncidentService. Rückführung in Risiken/Maßnahmen über die Pivots
 * isms_incident_risk / isms_incident_control.
 *
 * Datenschutz-Kopplung BEWUSST lose: personal_data_affected ist ein reiner
 * Hinweis auf eine SEPARATE Datenschutzmeldung; privacy_incident_ref hält
 * optional die ID/Sqid eines Privacy\Incident (WIP) als Freitext — KEIN
 * FK-Constraint, die Fallakten bleiben getrennt.
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $isms_scope_id
 * @property int $incident_no
 * @property string $title
 * @property string|null $description
 * @property SecurityIncidentCategory $category
 * @property IncidentSeverity $severity
 * @property SecurityIncidentStatus $status
 * @property Carbon|null $detected_at
 * @property Carbon|null $occurred_at
 * @property Carbon|null $contained_at
 * @property Carbon|null $closed_at
 * @property int|null $reporter_user_id
 * @property int|null $owner_user_id
 * @property string|null $impact
 * @property string|null $root_cause
 * @property string|null $lessons_learned
 * @property bool $personal_data_affected
 * @property string|null $privacy_incident_ref
 */
class IsmsSecurityIncident extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<IsmsSecurityIncidentFactory> */
    use HasFactory;
    use HasSqid;

    use SoftDeletes;

    protected $table = 'isms_security_incidents';

    protected $fillable = [
        'organization_id',
        'isms_scope_id',
        'incident_no',
        'title',
        'description',
        'category',
        'severity',
        'status',
        'detected_at',
        'occurred_at',
        'contained_at',
        'closed_at',
        'reporter_user_id',
        'owner_user_id',
        'impact',
        'root_cause',
        'lessons_learned',
        'personal_data_affected',
        'privacy_incident_ref',
    ];

    protected $casts = [
        'incident_no' => 'integer',
        'category' => SecurityIncidentCategory::class,
        'severity' => IncidentSeverity::class,
        'status' => SecurityIncidentStatus::class,
        'detected_at' => 'datetime',
        'occurred_at' => 'datetime',
        'contained_at' => 'datetime',
        'closed_at' => 'datetime',
        'personal_data_affected' => 'boolean',
    ];

    /** Anzeige-Kennung im Register (z. B. "SI-12"). */
    public function displayNo(): string {
        return 'SI-' . $this->incident_no;
    }

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** @return BelongsTo<IsmsScope, $this> */
    public function scope(): BelongsTo {
        return $this->belongsTo(IsmsScope::class, 'isms_scope_id');
    }

    /** @return BelongsToMany<IsmsRisk, $this> */
    public function risks(): BelongsToMany {
        return $this->belongsToMany(IsmsRisk::class, 'isms_incident_risk', 'incident_id', 'risk_id');
    }

    /** @return BelongsToMany<IsmsControl, $this> */
    public function controls(): BelongsToMany {
        return $this->belongsToMany(IsmsControl::class, 'isms_incident_control', 'incident_id', 'control_id');
    }

    /**
     * Offene Vorfälle (alles außer geschlossen).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOpen(Builder $query): Builder {
        return $query->where('status', '!=', SecurityIncidentStatus::Closed->value);
    }

    /**
     * Offene kritische Vorfälle (Dashboard-KPI / Eskalation).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOpenCritical(Builder $query): Builder {
        return $query->open()->where('severity', IncidentSeverity::Critical->value);
    }
}
