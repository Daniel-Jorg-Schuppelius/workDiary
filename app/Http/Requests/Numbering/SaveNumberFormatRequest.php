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
use App\Services\Numbering\NumberAuthority;
use Illuminate\Validation\Rule;

class SaveNumberFormatRequest extends BaseFormRequest {
    use ResolvesCurrentOrganization;

    /** Kreise, deren vergebende Stelle auf der Seite gewählt wird (MVP-1130). */
    public const SELECTABLE_SOURCE_SCOPES = [NumberScope::Customer, NumberScope::Supplier];

    /** @return array<string, array<int, mixed>> */
    public function rules(): array {
        return [
            'scope' => ['required', Rule::enum(NumberScope::class)],
            'source' => ['nullable', 'string', Rule::in($this->allowedSources())],
            'prefix' => ['nullable', 'string', 'max:16', 'regex:/^[A-Z0-9-]*$/i'],
            'prefix_separator' => ['nullable', 'string', 'max:4'],
            'include_year' => ['sometimes', 'boolean'],
            'year_separator' => ['nullable', 'string', 'max:4'],
            'padding' => ['required', 'integer', 'min:1', 'max:10'],
            'reset_per_year' => ['sometimes', 'boolean'],
            'starts_at' => ['required', 'integer', 'min:0', 'max:999999999999'],
        ];
    }

    /**
     * `source` heißt global „Wird gelöscht“ (Zusammenführen); hier ist es die vergebende Stelle.
     *
     * @return array<string, string>
     */
    public function attributes(): array {
        return ['source' => (string) __('numbering.source.column')];
    }

    /** @return list<string> */
    private function allowedSources(): array {
        $scope = NumberScope::tryFrom((string) $this->input('scope'));
        $organization = $this->currentOrganizationOrUserOrganization();
        if (! in_array($scope, self::SELECTABLE_SOURCE_SCOPES, true) || ! $organization instanceof Organization) {
            return [];
        }

        return array_keys(app(NumberAuthority::class)->sourceOptions($organization, $scope));
    }
}
