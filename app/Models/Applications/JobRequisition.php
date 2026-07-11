<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JobRequisition.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Applications;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Stellenbedarf/Stellenprofil (Feature 068, MVP-189): Basis für
 * Veröffentlichungen (job_postings) und Bewerbungsakten.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $title
 * @property string|null $department
 * @property string|null $profile
 * @property int $headcount
 * @property string $employment_type
 * @property string|null $budget_note
 * @property string $status
 * @property int|null $responsible_user_id
 * @property \Illuminate\Support\Carbon|null $target_start_on
 * @property int|null $created_by
 */
class JobRequisition extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    public const STATUSES = ['draft', 'open', 'on_hold', 'filled', 'closed'];

    public const EMPLOYMENT_TYPES = ['full_time', 'part_time', 'apprentice', 'freelance'];

    protected $fillable = [
        'organization_id', 'title', 'department', 'profile', 'headcount',
        'employment_type', 'budget_note', 'status', 'responsible_user_id',
        'target_start_on', 'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'headcount' => 'integer',
        'target_start_on' => 'date',
    ];

    /** @return HasMany<JobPosting, $this> */
    public function postings(): HasMany {
        return $this->hasMany(JobPosting::class, 'job_requisition_id');
    }

    /** @return HasMany<JobApplication, $this> */
    public function applications(): HasMany {
        return $this->hasMany(JobApplication::class, 'job_requisition_id');
    }

    /** @return BelongsTo<User, $this> */
    public function responsible(): BelongsTo {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }
}
