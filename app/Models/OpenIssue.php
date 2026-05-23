<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenIssue.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\OpenIssue\OpenIssueSeverity;
use App\Enums\OpenIssue\OpenIssueSource;
use App\Enums\OpenIssue\OpenIssueStatus;
use App\Enums\OpenIssue\OpenIssueVisibility;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasAttachments;
use Database\Factories\OpenIssueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $subject_type
 * @property int $subject_id
 * @property OpenIssueSource $source_type
 * @property int|null $source_ref_id
 * @property string $title
 * @property string|null $description
 * @property string|null $category
 * @property OpenIssueSeverity $severity
 * @property OpenIssueStatus $status
 * @property int|null $assignee_user_id
 * @property \Illuminate\Support\Carbon|null $due_at
 * @property OpenIssueVisibility $visibility
 * @property \Illuminate\Support\Carbon|null $closed_at
 * @property int|null $closed_by_user_id
 * @property string|null $closed_reason
 * @property int $created_by_user_id
 */
class OpenIssue extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasAttachments;
    use SoftDeletes;

    /** @use HasFactory<OpenIssueFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'subject_type',
        'subject_id',
        'source_type',
        'source_ref_id',
        'title',
        'description',
        'category',
        'severity',
        'status',
        'assignee_user_id',
        'due_at',
        'visibility',
        'closed_at',
        'closed_by_user_id',
        'closed_reason',
        'created_by_user_id',
    ];

    protected $casts = [
        'source_type' => OpenIssueSource::class,
        'severity' => OpenIssueSeverity::class,
        'status' => OpenIssueStatus::class,
        'visibility' => OpenIssueVisibility::class,
        'due_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo {
        return $this->belongsTo(User::class, 'assignee_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function closer(): BelongsTo {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    /** @return HasMany<OpenIssueEvent, $this> */
    public function events(): HasMany {
        return $this->hasMany(OpenIssueEvent::class)->orderBy('created_at');
    }
}
