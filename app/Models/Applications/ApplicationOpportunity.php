<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApplicationOpportunity.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Applications;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\{Customer, Project, Quote, User};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, MorphMany};

/**
 * Ausschreibungsakte / Auftragsbewerbung (Feature 068, MVP-184): vorgelagerte
 * Fallakte mit Fristen, Go-/No-go, Unterlagen-Checkliste, versionierten
 * Einreichungspaketen und kontrollierter Überführung nach Gewinn.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $title
 * @property string $kind
 * @property string|null $source
 * @property int|null $customer_id
 * @property int|null $project_id
 * @property int|null $quote_id
 * @property int|null $bill_of_quantity_id
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $question_deadline
 * @property \Illuminate\Support\Carbon|null $submission_deadline
 * @property \Illuminate\Support\Carbon|null $decision_expected_on
 * @property string|null $estimated_value
 * @property int|null $probability
 * @property string|null $risk_note
 * @property string $go_decision
 * @property int|null $go_decided_by
 * @property \Illuminate\Support\Carbon|null $go_decided_at
 * @property string|null $go_note
 * @property string|null $loss_reason
 * @property int|null $responsible_user_id
 * @property string|null $description
 * @property int|null $created_by
 */
class ApplicationOpportunity extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    public const KINDS = ['inquiry', 'tender', 'participation', 'framework', 'direct', 'recurring'];

    public const STATUSES = ['captured', 'screened', 'in_progress', 'question', 'submitted', 'post_submission', 'won', 'lost', 'withdrawn', 'archived'];

    /** Offene (Pipeline-)Status — Entscheidungen sind endgültig. */
    public const OPEN_STATUSES = ['captured', 'screened', 'in_progress', 'question', 'submitted', 'post_submission'];

    protected $fillable = [
        'organization_id', 'title', 'kind', 'source', 'customer_id', 'project_id',
        'quote_id', 'bill_of_quantity_id', 'status', 'question_deadline',
        'submission_deadline', 'decision_expected_on', 'estimated_value',
        'probability', 'risk_note', 'go_decision', 'go_decided_by', 'go_decided_at',
        'go_note', 'loss_reason', 'responsible_user_id', 'description', 'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'question_deadline' => 'date',
        'submission_deadline' => 'date',
        'decision_expected_on' => 'date',
        'go_decided_at' => 'datetime',
        'probability' => 'integer',
    ];

    /** @return HasMany<ApplicationRequirement, $this> */
    public function requirements(): HasMany {
        return $this->hasMany(ApplicationRequirement::class, 'application_opportunity_id')->orderBy('position');
    }

    /** @return HasMany<ApplicationSubmission, $this> */
    public function submissions(): HasMany {
        return $this->hasMany(ApplicationSubmission::class, 'application_opportunity_id')->orderByDesc('version');
    }

    /** @return MorphMany<ApplicationContractNegotiation, $this> */
    public function negotiations(): MorphMany {
        return $this->morphMany(ApplicationContractNegotiation::class, 'negotiable');
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Quote, $this> */
    public function quote(): BelongsTo {
        return $this->belongsTo(Quote::class);
    }

    /** @return BelongsTo<User, $this> */
    public function responsible(): BelongsTo {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function isOpen(): bool {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }
}
