<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveCommissionRuleRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Sales\{CommissionScope, LeadSource};
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\User;
use App\Rules\ExistsInCurrentOrganization;
use Illuminate\Validation\Rule;

/**
 * Provisionsregel anlegen/bearbeiten (Feature 146, MVP-729). Der
 * Geltungsbereich entscheidet, welches Zusatzfeld Pflicht ist: `user` braucht
 * die Vertriebsperson, `lead_source`/`product_group` den Bereichswert.
 */
class SaveCommissionRuleRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'user_id' => User::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        $scope = (string) $this->input('scope', CommissionScope::All->value);

        return [
            'name' => ['required', 'string', 'max:120'],
            'scope' => ['required', Rule::in(CommissionScope::values())],
            'scope_value' => [
                CommissionScope::tryFrom($scope)?->needsValue() === true ? 'required' : 'nullable',
                'string', 'max:120',
                // Bei Lead-Quelle muss der Wert ein LeadSource-Schluessel sein:
                // ein freier Text passte auf keinen Beleg und die Regel bliebe
                // still wirkungslos.
                ...($scope === CommissionScope::LeadSource->value ? [Rule::in(LeadSource::values())] : []),
            ],
            'user_id' => [
                $scope === CommissionScope::User->value ? 'required' : 'nullable',
                'integer', new ExistsInCurrentOrganization('users'),
            ],
            // Satz als Prozentwert; 0 ist erlaubt (bewusste Nullregel, die eine
            // allgemeinere Regel fuer einen Bereich aussetzt).
            'rate_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'priority' => ['required', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
