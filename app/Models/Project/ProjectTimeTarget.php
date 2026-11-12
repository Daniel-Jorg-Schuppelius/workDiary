<?php
/*
 * Created on   : Tue Nov 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectTimeTarget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Project;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Platform\User;
use Database\Factories\Project\ProjectTimeTargetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Sollzeit je Projekt und Tag (Feature 194): eine Zeile gilt für eine Person
 * oder als Projektvorgabe (user_id null), an einem Wochentag oder an einem
 * festen Datum, innerhalb ihrer Gültigkeit.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $project_id
 * @property int|null $user_id
 * @property int|null $weekday ISO 1–7
 * @property Carbon|null $specific_date
 * @property int $target_minutes
 * @property Carbon|null $valid_from
 * @property Carbon|null $valid_until
 * @property string|null $note
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property-read Project $project
 * @property-read User|null $user
 */
class ProjectTimeTarget extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<ProjectTimeTargetFactory> */
    use HasFactory;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'project_id', 'user_id', 'weekday', 'specific_date',
        'target_minutes', 'valid_from', 'valid_until', 'note', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'weekday' => 'integer',
        'specific_date' => 'date',
        'target_minutes' => 'integer',
        'valid_from' => 'date',
        'valid_until' => 'date',
    ];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    public function isProjectDefault(): bool {
        return $this->user_id === null;
    }

    public function appliesOn(Carbon $day): bool {
        $date = $day->toDateString();
        if ($this->valid_from !== null && $this->valid_from->toDateString() > $date) {
            return false;
        }
        if ($this->valid_until !== null && $this->valid_until->toDateString() < $date) {
            return false;
        }
        if ($this->specific_date !== null) {
            return $this->specific_date->toDateString() === $date;
        }

        return $this->weekday === $day->dayOfWeekIso;
    }
}
