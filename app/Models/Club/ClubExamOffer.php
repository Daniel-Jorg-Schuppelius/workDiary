<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubExamOffer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Club;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\{Event, User};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany};
use Illuminate\Support\Carbon;

/**
 * Prüfungsangebot (MVP-847): ein Termin der Art „Prüfung“ mit eingefrorener
 * Regelversion, Zielgraden und Prüfern.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $event_id
 * @property int $club_grading_system_id
 * @property int $club_grading_version_id
 * @property list<int>|null $examiner_user_ids
 * @property string|null $notes
 * @property Carbon|null $results_released_at
 */
class ClubExamOffer extends Model {
    use Auditable;

    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = ['organization_id', 'event_id', 'club_grading_system_id', 'club_grading_version_id', 'examiner_user_ids', 'notes', 'results_released_at'];

    protected $casts = ['examiner_user_ids' => 'array', 'results_released_at' => 'datetime'];

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<ClubGradingSystem, $this> */
    public function system(): BelongsTo {
        return $this->belongsTo(ClubGradingSystem::class, 'club_grading_system_id');
    }

    /** @return BelongsTo<ClubGradingVersion, $this> */
    public function version(): BelongsTo {
        return $this->belongsTo(ClubGradingVersion::class, 'club_grading_version_id');
    }

    /** @return BelongsToMany<ClubGrade, $this> */
    public function targetGrades(): BelongsToMany {
        return $this->belongsToMany(ClubGrade::class, 'club_exam_offer_grades', 'club_exam_offer_id', 'club_grade_id')->orderBy('rank');
    }

    /** @return HasMany<ClubExamCandidate, $this> */
    public function candidates(): HasMany {
        return $this->hasMany(ClubExamCandidate::class, 'club_exam_offer_id');
    }

    /** @return list<int> */
    public function examinerIds(): array {
        return array_values(array_filter(array_map('intval', (array) ($this->examiner_user_ids ?? []))));
    }

    public function isExaminer(User $user): bool {
        return in_array((int) $user->id, $this->examinerIds(), true);
    }
}
