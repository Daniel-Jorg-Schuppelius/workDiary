<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveInvestmentCaseRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Investments;

use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidOrNumericInputs;
use App\Models\Investments\InvestmentCase;

/**
 * Validierung für Anlage/Bearbeitung einer Investitionsakte (Feature 069,
 * MVP-200) — Store und Update teilen dieselben Regeln. Berechtigung trägt
 * der Controller (InvestmentCasePolicy).
 */
class SaveInvestmentCaseRequest extends BaseFormRequest {
    use DecodesSqidOrNumericInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'responsible_user_id' => \App\Models\User::class,
        'cost_center_id' => \App\Models\CostCenter::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'title' => ['required', 'string', 'max:200'],
            'category' => ['required', 'in:' . implode(',', InvestmentCase::CATEGORIES)],
            'reason' => ['nullable', 'string', 'max:5000'],
            'objective' => ['nullable', 'string', 'max:5000'],
            'urgency' => ['required', 'in:low,medium,high'],
            'risk_note' => ['nullable', 'string', 'max:5000'],
            'responsible_user_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('users')],
            'cost_center_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('cost_centers')],
            'cost_center_label' => ['nullable', 'string', 'max:200'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ];
    }
}
