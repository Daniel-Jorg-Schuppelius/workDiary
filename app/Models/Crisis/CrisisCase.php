<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CrisisCase.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Crisis;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasOne};

/**
 * Krisenakte (Feature 070, MVP-212): koordinierter Führungszustand über
 * verknüpften Fachvorgängen — Lagebild, Stab, Entscheidungen, Maßnahmen,
 * Kommunikation, Wiederanlauf und Nachbereitung. Fachobjekte bleiben in
 * ihren führenden Modulen (Verknüpfung statt Kopie).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $title
 * @property string $category
 * @property string $severity
 * @property string $status
 * @property string|null $trigger_source
 * @property string|null $description
 * @property string|null $affected_summary
 * @property int|null $responsible_user_id
 * @property \Illuminate\Support\Carbon|null $activated_at
 * @property \Illuminate\Support\Carbon|null $all_clear_at
 * @property \Illuminate\Support\Carbon|null $closed_at
 * @property int|null $created_by
 */
class CrisisCase extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    public const CATEGORIES = ['it_outage', 'security', 'privacy', 'safety', 'infrastructure', 'supply', 'other'];

    public const SEVERITIES = ['minor', 'major', 'critical'];

    public const STATUSES = ['prepared', 'reported', 'assessed', 'activated', 'in_progress', 'stabilized', 'recovery', 'all_clear', 'post_review', 'closed', 'discarded'];

    /** Aktive Führungszustände (Dashboard + Mitglieder-Notfallzugriff). */
    public const ACTIVE_STATUSES = ['reported', 'assessed', 'activated', 'in_progress', 'stabilized', 'recovery'];

    protected $fillable = [
        'organization_id', 'title', 'category', 'severity', 'status',
        'trigger_source', 'description', 'affected_summary',
        'responsible_user_id', 'activated_at', 'all_clear_at', 'closed_at',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'activated_at' => 'datetime',
        'all_clear_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    /** @return HasMany<CrisisCaseLink, $this> */
    public function links(): HasMany {
        return $this->hasMany(CrisisCaseLink::class, 'crisis_case_id');
    }

    /** @return HasMany<CrisisTeamAssignment, $this> */
    public function team(): HasMany {
        return $this->hasMany(CrisisTeamAssignment::class, 'crisis_case_id');
    }

    /** @return HasMany<CrisisSituationReport, $this> */
    public function situationReports(): HasMany {
        return $this->hasMany(CrisisSituationReport::class, 'crisis_case_id')->orderByDesc('version');
    }

    /** @return HasMany<CrisisDecision, $this> */
    public function decisions(): HasMany {
        return $this->hasMany(CrisisDecision::class, 'crisis_case_id')->orderByDesc('decided_at');
    }

    /** @return HasMany<CrisisAction, $this> */
    public function actions(): HasMany {
        return $this->hasMany(CrisisAction::class, 'crisis_case_id');
    }

    /** @return HasMany<CrisisCommunication, $this> */
    public function communications(): HasMany {
        return $this->hasMany(CrisisCommunication::class, 'crisis_case_id');
    }

    /** @return HasMany<CrisisContinuityImpact, $this> */
    public function continuityImpacts(): HasMany {
        return $this->hasMany(CrisisContinuityImpact::class, 'crisis_case_id');
    }

    /** @return HasOne<CrisisReview, $this> */
    public function review(): HasOne {
        return $this->hasOne(CrisisReview::class, 'crisis_case_id');
    }

    /** @return BelongsTo<User, $this> */
    public function responsible(): BelongsTo {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function isActive(): bool {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }

    /** Notfallzugriff (MVP-213): Stabsmitglieder sehen die Akte während der Krise. */
    public function isTeamMember(User $user): bool {
        return $this->team()
            ->where(fn($q) => $q->where('user_id', $user->id)->orWhere('deputy_user_id', $user->id))
            ->exists();
    }
}
