<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AddInvestmentOptionRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Investments;

use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidOrNumericInputs;

/**
 * Validierung für eine Variante im Variantenvergleich (MVP-201).
 * Berechtigung trägt der Controller (InvestmentCasePolicy).
 */
class AddInvestmentOptionRequest extends BaseFormRequest {
    use DecodesSqidOrNumericInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'supplier_id' => \App\Models\Supplier::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'title' => ['required', 'string', 'max:200'],
            'supplier_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('suppliers')],
            'one_time_cost' => ['required', 'numeric', 'min:0'],
            'recurring_cost_yearly' => ['nullable', 'numeric', 'min:0'],
            'delivery_weeks' => ['nullable', 'integer', 'min:0', 'max:520'],
            'useful_life_years' => ['nullable', 'integer', 'min:0', 'max:99'],
            'quality_score' => ['nullable', 'integer', 'min:1', 'max:5'],
            'risk_note' => ['nullable', 'string', 'max:1000'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
