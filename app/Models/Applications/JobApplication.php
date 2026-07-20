<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JobApplication.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Applications;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasOne, MorphMany};

/**
 * Bewerbungsakte (Feature 068, MVP-190): Kandidaten-PII verschlüsselt at
 * rest (Feature 016), Dubletten-Lookup über email_hash, eigener
 * Rechtebereich (recruiting.*) — KEINE Mitarbeiterstammdaten bis zur
 * kontrollierten Überführung (EmployeeDraft).
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $job_requisition_id
 * @property int|null $job_posting_id
 * @property string|null $candidate_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $email_hash
 * @property string $source
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $received_at
 * @property \Illuminate\Support\Carbon|null $consent_talent_pool_at
 * @property \Illuminate\Support\Carbon|null $consent_expires_on
 * @property \Illuminate\Support\Carbon|null $retention_until
 * @property string|null $notes
 * @property int|null $responsible_user_id
 * @property \Illuminate\Support\Carbon|null $anonymized_at
 * @property int|null $created_by
 */
#[Hidden(['candidate_name', 'email', 'phone', 'notes', 'email_hash'])]
class JobApplication extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    public const STATUSES = ['received', 'screened', 'interview_planned', 'interviewed', 'task_open', 'offer', 'accepted', 'rejected', 'withdrawn', 'talent_pool', 'deleted'];

    /** Aktive Pipeline-Status (vor der Entscheidung). */
    public const PIPELINE_STATUSES = ['received', 'screened', 'interview_planned', 'interviewed', 'task_open', 'offer'];

    protected $fillable = [
        'organization_id', 'job_requisition_id', 'job_posting_id',
        'candidate_name', 'email', 'phone', 'email_hash', 'source', 'status',
        'received_at', 'consent_talent_pool_at', 'consent_expires_on',
        'retention_until', 'notes', 'responsible_user_id', 'anonymized_at',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        // PII verschlüsselt (APP_KEY); leere Strings IMMER als NULL speichern
        // (leerer encrypted-String → "payload invalid" beim Lesen).
        'candidate_name' => 'encrypted',
        'email' => 'encrypted',
        'phone' => 'encrypted',
        'notes' => 'encrypted',
        'received_at' => 'datetime',
        'consent_talent_pool_at' => 'datetime',
        'consent_expires_on' => 'date',
        'retention_until' => 'date',
        'anonymized_at' => 'datetime',
    ];

    /** Deterministischer Lookup-Hash für die Dublettenprüfung (MVP-190). */
    public static function hashEmail(string $email): string {
        return CryptoHelper::hash(mb_strtolower(trim($email)));
    }

    /** @return BelongsTo<JobRequisition, $this> */
    public function requisition(): BelongsTo {
        return $this->belongsTo(JobRequisition::class, 'job_requisition_id');
    }

    /** @return BelongsTo<JobPosting, $this> */
    public function posting(): BelongsTo {
        return $this->belongsTo(JobPosting::class, 'job_posting_id');
    }

    /** @return HasMany<JobApplicationDocument, $this> */
    public function documents(): HasMany {
        return $this->hasMany(JobApplicationDocument::class, 'job_application_id');
    }

    /** @return HasMany<JobApplicationInterview, $this> */
    public function interviews(): HasMany {
        return $this->hasMany(JobApplicationInterview::class, 'job_application_id')->orderBy('scheduled_at');
    }

    /** @return HasMany<JobApplicationReview, $this> */
    public function reviews(): HasMany {
        return $this->hasMany(JobApplicationReview::class, 'job_application_id');
    }

    /** @return MorphMany<ApplicationContractNegotiation, $this> */
    public function negotiations(): MorphMany {
        return $this->morphMany(ApplicationContractNegotiation::class, 'negotiable');
    }

    /** @return HasOne<EmployeeDraft, $this> */
    public function employeeDraft(): HasOne {
        return $this->hasOne(EmployeeDraft::class, 'job_application_id');
    }

    /** @return BelongsTo<User, $this> */
    public function responsible(): BelongsTo {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function isAnonymized(): bool {
        return $this->anonymized_at !== null;
    }

    /** DaisyUI badge tone */
    public function statusTone(): string {
        return match ($this->status) {
            'screened' => 'info',
            'interview_planned', 'interviewed' => 'primary',
            'task_open' => 'warning',
            'offer' => 'accent',
            'accepted' => 'success',
            'rejected' => 'error',
            'talent_pool' => 'secondary',
            'withdrawn', 'deleted' => 'neutral',
            default => 'ghost',
        };
    }
}
