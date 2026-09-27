<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvestmentProgramBudget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Investments;

use App\Models\Concerns\{Auditable, BelongsToOrganization};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jahresbudget eines Investitionsprogramms (MVP-927).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $investment_program_id
 * @property int $year
 * @property numeric-string $budget_amount
 */
class InvestmentProgramBudget extends Model {
    use Auditable;
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'investment_program_id', 'year', 'budget_amount'];

    /** @var array<string, string> */
    protected $casts = ['year' => 'integer', 'budget_amount' => 'decimal:2'];

    /** @return BelongsTo<InvestmentProgram, $this> */
    public function program(): BelongsTo {
        return $this->belongsTo(InvestmentProgram::class, 'investment_program_id');
    }
}
