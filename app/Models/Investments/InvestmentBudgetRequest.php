<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvestmentBudgetRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Investments;

use App\Models\Approval;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphMany};

/**
 * Budgetantrag (Feature 069, MVP-202/203): versioniert — genehmigte
 * Stände werden NIE überschrieben; eine Erhöhung ist ein neuer Antrag,
 * der den alten als superseded markiert. Freigaben laufen über das
 * Approval-Modell (Schwellenwert-Kette, Selbstfreigabe-Sperre).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $investment_case_id
 * @property int $version
 * @property string $amount
 * @property string $cost_kind
 * @property string $financing
 * @property string|null $payment_plan
 * @property string|null $note
 * @property string $status
 * @property array<string, mixed>|null $snapshot
 * @property int|null $requested_by
 * @property \Illuminate\Support\Carbon|null $decided_at
 */
class InvestmentBudgetRequest extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    public const STATUSES = ['draft', 'in_approval', 'approved', 'rejected', 'superseded'];

    protected $fillable = [
        'organization_id', 'investment_case_id', 'version', 'amount',
        'cost_kind', 'financing', 'payment_plan', 'note', 'status',
        'snapshot', 'requested_by', 'decided_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'version' => 'integer',
        'amount' => 'decimal:2',
        'snapshot' => 'array',
        'decided_at' => 'datetime',
    ];

    /** @return BelongsTo<InvestmentCase, $this> */
    public function investmentCase(): BelongsTo {
        return $this->belongsTo(InvestmentCase::class, 'investment_case_id');
    }

    /** @return MorphMany<Approval, $this> */
    public function approvals(): MorphMany {
        return $this->morphMany(Approval::class, 'approvable');
    }
}
