<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvestmentCase.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Investments;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\{CostCenter, Project, User};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasOne};

/**
 * Investitionsakte (Feature 069, MVP-200): Entscheidungs- und
 * Steuerungsobjekt — ersetzt weder Projekt noch Bestellung noch Asset.
 * Folgeobjekte entstehen erst NACH der Freigabe (investment_links).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $title
 * @property string $category
 * @property string|null $reason
 * @property string|null $objective
 * @property string $urgency
 * @property string|null $risk_note
 * @property string $status
 * @property int|null $responsible_user_id
 * @property int|null $cost_center_id
 * @property string|null $cost_center_label
 * @property int|null $project_id
 * @property \Illuminate\Support\Carbon|null $starts_on
 * @property \Illuminate\Support\Carbon|null $ends_on
 * @property int|null $created_by
 */
class InvestmentCase extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    public const CATEGORIES = ['replacement', 'expansion', 'project', 'machine', 'it', 'infrastructure', 'inventory', 'compliance'];

    public const STATUSES = ['idea', 'screening', 'comparison', 'budget_request', 'in_approval', 'approved', 'rejected', 'deferred', 'in_progress', 'completed', 'cancelled', 'post_review'];

    /** Vor der Freigabe editierbare Phasen. */
    public const PLANNING_STATUSES = ['idea', 'screening', 'comparison', 'budget_request'];

    protected $fillable = [
        'organization_id', 'title', 'category', 'reason', 'objective', 'urgency',
        'risk_note', 'status', 'responsible_user_id', 'cost_center_id',
        'cost_center_label', 'project_id', 'starts_on', 'ends_on', 'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];

    /** @return HasMany<InvestmentOption, $this> */
    public function options(): HasMany {
        return $this->hasMany(InvestmentOption::class, 'investment_case_id');
    }

    /** @return HasMany<InvestmentBudgetRequest, $this> */
    public function budgetRequests(): HasMany {
        return $this->hasMany(InvestmentBudgetRequest::class, 'investment_case_id')->orderByDesc('version');
    }

    /** @return HasMany<InvestmentLink, $this> */
    public function links(): HasMany {
        return $this->hasMany(InvestmentLink::class, 'investment_case_id');
    }

    /** @return HasMany<InvestmentActual, $this> */
    public function actuals(): HasMany {
        return $this->hasMany(InvestmentActual::class, 'investment_case_id')->orderBy('occurred_on');
    }

    /** @return HasMany<InvestmentDeviation, $this> */
    public function deviations(): HasMany {
        return $this->hasMany(InvestmentDeviation::class, 'investment_case_id');
    }

    /** @return HasOne<InvestmentReview, $this> */
    public function review(): HasOne {
        return $this->hasOne(InvestmentReview::class, 'investment_case_id');
    }

    /** @return BelongsTo<CostCenter, $this> */
    public function costCenter(): BelongsTo {
        return $this->belongsTo(CostCenter::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function responsible(): BelongsTo {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /** Genehmigter Budgetantrag (aktueller Stand, nicht superseded). */
    public function approvedBudget(): ?InvestmentBudgetRequest {
        /** @var InvestmentBudgetRequest|null $request */
        $request = $this->budgetRequests()->where('status', 'approved')->orderByDesc('version')->first();

        return $request;
    }

    /** Anzeige-Label der Kostenstelle (D2: FK vor Fallback-Label). */
    public function costCenterDisplay(): ?string {
        $center = $this->costCenter;
        if ($center !== null) {
            return $center->code . ' — ' . $center->label;
        }

        return $this->cost_center_label;
    }
}
