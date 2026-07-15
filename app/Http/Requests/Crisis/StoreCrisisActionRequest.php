<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StoreCrisisActionRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Crisis;

use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidOrNumericInputs;

/**
 * Validierung für das Erfassen einer Maßnahme (MVP-216).
 * Berechtigung trägt der Controller (CrisisCasePolicy).
 */
class StoreCrisisActionRequest extends BaseFormRequest {
    use DecodesSqidOrNumericInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'assignee_id' => \App\Models\User::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'title' => ['required', 'string', 'max:300'],
            'assignee_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('users')],
            'due_at' => ['nullable', 'date'],
            'priority' => ['required', 'in:low,medium,high'],
        ];
    }
}
