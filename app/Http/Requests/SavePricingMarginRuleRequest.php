<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SavePricingMarginRuleRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Rules\ExistsInCurrentOrganization;
use Illuminate\Validation\Rule;

/**
 * Validierung einer Margenregel (Feature 050, MVP-095). Lieferant ist optional
 * (globale Regel) und wird mandantensicher geprüft; die Berechtigung trägt der
 * Controller. Zielmarge ODER Aufschlag steuert den Vorschlag.
 */
class SavePricingMarginRuleRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'supplier' => \App\Models\Supplier::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'name' => ['required', 'string', 'max:191'],
            'supplier' => ['nullable', 'integer', new ExistsInCurrentOrganization('suppliers')],
            'category' => ['nullable', 'string', 'max:191'],
            'markup_percent' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'target_margin' => ['nullable', 'numeric', 'min:0', 'max:99.9'],
            'min_margin' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'min_sale_price' => ['nullable', 'numeric', 'min:0'],
            'rounding' => ['required', Rule::in(['none', 'up_0_05', 'up_0_10', 'up_0_50', 'up_0_99', 'up_1'])],
            'priority' => ['nullable', 'integer', 'min:0'],
            'active' => ['nullable', 'boolean'],
        ];
    }
}
