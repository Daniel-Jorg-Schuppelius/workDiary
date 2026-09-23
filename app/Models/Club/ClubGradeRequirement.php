<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubGradeRequirement.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Club;

use App\Enums\Club\{ClubCountingBasis, ClubEventKind};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Voraussetzungen je Zielgrad und Regelversion (MVP-846) — eine UND-Liste:
 * Vorgrad, Mindestanwesenheit (Minuten und/oder Termine), Zählzeitraum,
 * Wartezeit, Mindestalter, Pflichtlehrgang, fachliche Freigabe.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $club_grading_version_id
 * @property int $club_grade_id
 * @property int|null $previous_grade_id
 * @property int|null $min_minutes
 * @property int|null $min_sessions
 * @property int|null $min_minutes_per_session
 * @property ClubCountingBasis $counting_basis
 * @property int|null $window_months
 * @property int|null $wait_months
 * @property int|null $min_age
 * @property list<string>|null $counted_event_kinds
 * @property list<int>|null $counted_group_ids
 * @property string|null $required_proof_label
 * @property bool $requires_approval
 * @property bool $allows_exception
 */
class ClubGradeRequirement extends Model {
    use Auditable;

    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'club_grading_version_id', 'club_grade_id', 'previous_grade_id',
        'min_minutes', 'min_sessions', 'min_minutes_per_session', 'counting_basis', 'window_months',
        'wait_months', 'min_age', 'counted_event_kinds', 'counted_group_ids', 'required_proof_label',
        'requires_approval', 'allows_exception',
    ];

    protected $casts = [
        'min_minutes' => 'integer',
        'min_sessions' => 'integer',
        'min_minutes_per_session' => 'integer',
        'counting_basis' => ClubCountingBasis::class,
        'window_months' => 'integer',
        'wait_months' => 'integer',
        'min_age' => 'integer',
        'counted_event_kinds' => 'array',
        'counted_group_ids' => 'array',
        'requires_approval' => 'boolean',
        'allows_exception' => 'boolean',
    ];

    /** @return BelongsTo<ClubGradingVersion, $this> */
    public function version(): BelongsTo {
        return $this->belongsTo(ClubGradingVersion::class, 'club_grading_version_id');
    }

    /** @return BelongsTo<ClubGrade, $this> */
    public function grade(): BelongsTo {
        return $this->belongsTo(ClubGrade::class, 'club_grade_id');
    }

    /** @return BelongsTo<ClubGrade, $this> */
    public function previousGrade(): BelongsTo {
        return $this->belongsTo(ClubGrade::class, 'previous_grade_id');
    }

    /**
     * Anrechenbare Terminarten: konfiguriert, sonst Training und Lehrgang.
     *
     * @return list<string>
     */
    public function countedKinds(): array {
        $kinds = array_values(array_filter((array) ($this->counted_event_kinds ?? []), 'is_string'));

        return $kinds !== [] ? $kinds : [ClubEventKind::Training->value, ClubEventKind::Course->value];
    }

    public function hasAttendanceRule(): bool {
        return $this->min_minutes !== null || $this->min_sessions !== null;
    }
}
