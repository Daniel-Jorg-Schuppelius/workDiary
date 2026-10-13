<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveNumberFormatRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests\Numbering;

use App\Enums\Numbering\NumberScope;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Requests\BaseFormRequest;
use App\Models\Platform\Organization;
use App\Services\Numbering\{NumberAuthority, NumberFormatOverview};
use Illuminate\Validation\Rule;

/**
 * Alle Nummernkreise in einem Absenden (MVP-1147). Felder gesperrter Kreise
 * sendet der Browser nicht mit; jedes Formatfeld ist deshalb optional.
 */
class SaveNumberFormatRequest extends BaseFormRequest {
    use ResolvesCurrentOrganization;

    /** Formatfelder einer Zeile. */
    public const FIELDS = ['prefix', 'prefix_separator', 'include_year', 'year_separator', 'padding', 'reset_per_year', 'starts_at', 'source'];

    /** @return array<string, array<int, mixed>> */
    public function rules(): array {
        $rules = [
            'formats' => ['required', 'array:' . implode(',', NumberScope::values())],
            'formats.*' => ['array'],
            'formats.*.prefix' => ['sometimes', 'nullable', 'string', 'max:16', 'regex:/^[A-Z0-9-]*$/i'],
            'formats.*.prefix_separator' => ['sometimes', 'nullable', 'string', 'max:4'],
            'formats.*.include_year' => ['sometimes', 'boolean'],
            'formats.*.year_separator' => ['sometimes', 'nullable', 'string', 'max:4'],
            'formats.*.padding' => ['sometimes', 'required', 'integer', 'min:1', 'max:10'],
            'formats.*.reset_per_year' => ['sometimes', 'boolean'],
            'formats.*.starts_at' => ['sometimes', 'required', 'integer', 'min:0', 'max:999999999999'],
            'separators' => ['sometimes', 'array'],
            'separators.prefix' => ['nullable', 'string', 'max:4'],
            'separators.year' => ['nullable', 'string', 'max:4'],
            'apply_separators' => ['sometimes', 'boolean'],
        ];

        $organization = $this->currentOrganizationOrUserOrganization();
        foreach (NumberScope::cases() as $scope) {
            $rules['formats.' . $scope->value . '.source'] = in_array($scope, NumberFormatOverview::SELECTABLE_SOURCE_SCOPES, true) && $organization instanceof Organization
                ? ['sometimes', 'nullable', 'string', Rule::in(array_keys(app(NumberAuthority::class)->sourceOptions($organization, $scope)))]
                : ['prohibited'];
        }

        return $rules;
    }

    /**
     * Ein Formular für alle Kreise: Die Meldung nennt den Kreis (E4).
     *
     * @return array<string, string>
     */
    public function attributes(): array {
        $names = (array) trans('validation.attributes');
        $attributes = [];
        foreach (NumberScope::cases() as $scope) {
            foreach (self::FIELDS as $field) {
                $attributes['formats.' . $scope->value . '.' . $field] = (string) __('numbering.field.row', [
                    'field' => (string) ($names['formats.*.' . $field] ?? $field),
                    'scope' => $scope->label(),
                ]);
            }
        }

        return $attributes;
    }
}
