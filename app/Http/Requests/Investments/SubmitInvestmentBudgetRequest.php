<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SubmitInvestmentBudgetRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Investments;

use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidOrNumericInputs;

/**
 * Validierung für das Einreichen eines Budgetantrags (MVP-202/203).
 * Berechtigung trägt der Controller (InvestmentCasePolicy).
 */
class SubmitInvestmentBudgetRequest extends BaseFormRequest {
    use DecodesSqidOrNumericInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'cost_center_id' => \App\Models\CostCenter::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999'],
            'cost_kind' => ['required', 'in:purchase,leasing,service,mixed'],
            'financing' => ['required', 'in:cash,loan,leasing,subsidy,mixed'],
            'cost_center_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('cost_centers')],
            'payment_plan' => ['nullable', 'string', 'max:5000'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
