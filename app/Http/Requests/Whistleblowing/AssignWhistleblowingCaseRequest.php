<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssignWhistleblowingCaseRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Whistleblowing;

use App\Enums\Whistleblowing\CaseRole;
use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\Platform\User;
use Illuminate\Validation\Rule;

/**
 * Validierung für die Zuweisung eines Bearbeiters zu einem
 * Hinweisgeber-Fall. Berechtigung trägt der Controller
 * (WhistleblowingCasePolicy::assign).
 */
class AssignWhistleblowingCaseRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> Der Widerruf sendet die Sqid, das Zuweisen die Benutzer-ID. */
    protected array $sqidFields = ['user_id' => User::class];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'user_id' => ['required', 'integer', new \App\Rules\ExistsInCurrentOrganization()],
            'role' => ['required', Rule::enum(CaseRole::class)],
        ];
    }
}
