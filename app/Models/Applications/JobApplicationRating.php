<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JobApplicationRating.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Applications;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Learning\Competency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Einschätzung einer Kompetenz je Bewerbung (MVP-924), Stufe im Rahmen der
 * Kompetenz (`max_level`).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $job_application_id
 * @property int $competency_id
 * @property int $level
 * @property string|null $note
 * @property int|null $rated_by
 */
class JobApplicationRating extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = ['organization_id', 'job_application_id', 'competency_id', 'level', 'note', 'rated_by'];

    /** @var array<string, string> */
    protected $casts = ['level' => 'integer'];

    /** @return BelongsTo<JobApplication, $this> */
    public function application(): BelongsTo {
        return $this->belongsTo(JobApplication::class, 'job_application_id');
    }

    /** @return BelongsTo<Competency, $this> */
    public function competency(): BelongsTo {
        return $this->belongsTo(Competency::class);
    }
}
