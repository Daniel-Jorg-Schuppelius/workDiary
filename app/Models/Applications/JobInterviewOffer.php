<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JobInterviewOffer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Applications;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Concerns\HasAccessToken;
use App\Models\Platform\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Terminangebot an einen Bewerber (MVP-925): mehrere Termine, Auswahl über
 * einen Link; der Token liegt nur als Abdruck vor.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $job_application_id
 * @property string $token_hash
 * @property list<string> $slots  UTC, ISO 8601
 * @property string $mode
 * @property int $duration_minutes
 * @property int|null $interviewer_user_id
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon|null $chosen_at
 * @property int|null $job_application_interview_id
 * @property int|null $created_by
 */
class JobInterviewOffer extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasAccessToken;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'job_application_id', 'token_hash', 'slots', 'mode', 'duration_minutes',
        'interviewer_user_id', 'expires_at', 'chosen_at', 'job_application_interview_id', 'created_by',
    ];

    /** @var list<string> */
    protected $hidden = ['token_hash'];

    /** @var array<string, string> */
    protected $casts = [
        'slots' => 'array',
        'duration_minutes' => 'integer',
        'expires_at' => 'datetime',
        'chosen_at' => 'datetime',
    ];

    /** @return BelongsTo<JobApplication, $this> */
    public function application(): BelongsTo {
        return $this->belongsTo(JobApplication::class, 'job_application_id');
    }

    /** @return BelongsTo<User, $this> */
    public function interviewer(): BelongsTo {
        return $this->belongsTo(User::class, 'interviewer_user_id');
    }

    /** @return BelongsTo<JobApplicationInterview, $this> */
    public function interview(): BelongsTo {
        return $this->belongsTo(JobApplicationInterview::class, 'job_application_interview_id');
    }

    public function isOpen(): bool {
        return $this->chosen_at === null && $this->expires_at->isFuture();
    }
}
