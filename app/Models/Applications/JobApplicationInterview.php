<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JobApplicationInterview.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Applications;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Gesprächsplanung (Feature 068, MVP-191): Termin, Modus, Interviewer,
 * verschlüsselte Notizen und Kurzbewertung.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $job_application_id
 * @property \Illuminate\Support\Carbon $scheduled_at
 * @property string $mode
 * @property int|null $interviewer_id
 * @property string $status
 * @property string|null $notes
 * @property int|null $rating
 */
#[Hidden(['notes'])]
class JobApplicationInterview extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    public const MODES = ['onsite', 'remote', 'phone'];

    public const STATUSES = ['planned', 'done', 'cancelled'];

    protected $fillable = [
        'organization_id', 'job_application_id', 'scheduled_at', 'mode',
        'interviewer_id', 'status', 'notes', 'rating',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'scheduled_at' => 'datetime',
        'notes' => 'encrypted',
        'rating' => 'integer',
    ];

    /** @return BelongsTo<JobApplication, $this> */
    public function application(): BelongsTo {
        return $this->belongsTo(JobApplication::class, 'job_application_id');
    }

    /** @return BelongsTo<User, $this> */
    public function interviewer(): BelongsTo {
        return $this->belongsTo(User::class, 'interviewer_id');
    }
}
