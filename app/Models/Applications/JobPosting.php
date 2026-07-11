<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JobPosting.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Applications;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Veröffentlichungskanal einer Stellenausschreibung (Feature 068, MVP-189).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $job_requisition_id
 * @property string $channel
 * @property string|null $reference
 * @property string|null $url
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property string $status
 */
class JobPosting extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    public const CHANNELS = ['website', 'portal', 'agency', 'social', 'print', 'referral', 'other'];

    public const STATUSES = ['draft', 'published', 'expired', 'closed'];

    protected $fillable = [
        'organization_id', 'job_requisition_id', 'channel', 'reference', 'url',
        'published_at', 'expires_at', 'status',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'published_at' => 'datetime',
        'expires_at' => 'date',
    ];

    /** @return BelongsTo<JobRequisition, $this> */
    public function requisition(): BelongsTo {
        return $this->belongsTo(JobRequisition::class, 'job_requisition_id');
    }
}
