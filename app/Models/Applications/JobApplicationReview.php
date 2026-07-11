<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JobApplicationReview.php
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
 * Bewertung einer Bewerbung (Feature 068, MVP-191): 1–5 Sterne +
 * verschlüsselter Kommentar, nur für recruiting.*-Berechtigte.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $job_application_id
 * @property int $reviewer_id
 * @property int $rating
 * @property string|null $comment
 */
#[Hidden(['comment'])]
class JobApplicationReview extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = ['organization_id', 'job_application_id', 'reviewer_id', 'rating', 'comment'];

    /** @var array<string, string> */
    protected $casts = [
        'rating' => 'integer',
        'comment' => 'encrypted',
    ];

    /** @return BelongsTo<JobApplication, $this> */
    public function application(): BelongsTo {
        return $this->belongsTo(JobApplication::class, 'job_application_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
