<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MarkWhistleblowingSubjectRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Whistleblowing;

use App\Http\Requests\BaseFormRequest;

/**
 * Validierung für das Markieren einer betroffenen Person (Fall-Sperre).
 * Berechtigung trägt der Controller (WhistleblowingCasePolicy::process).
 */
class MarkWhistleblowingSubjectRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'user_id' => ['required', 'integer', new \App\Rules\ExistsInCurrentOrganization()],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
