<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UpdateCrisisActionPredecessorRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests\Crisis;

use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidOrNumericInputs;

/**
 * Vorgänger einer Maßnahme ändern (MVP-1628); leer löst ihn. Dieselbe Akte und
 * Zyklusfreiheit prüft der Controller, die Berechtigung die CrisisCasePolicy.
 */
class UpdateCrisisActionPredecessorRequest extends BaseFormRequest {
    use DecodesSqidOrNumericInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'depends_on_id' => \App\Models\Crisis\CrisisAction::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'depends_on_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('crisis_actions')],
        ];
    }
}
