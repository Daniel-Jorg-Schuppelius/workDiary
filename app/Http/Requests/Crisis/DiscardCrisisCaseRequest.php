<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DiscardCrisisCaseRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Crisis;

use App\Http\Requests\BaseFormRequest;

/**
 * Verwerfen einer Fehlmeldung (MVP-1517): ohne Begründung kein Verwerfen.
 * Berechtigung trägt der Controller (CrisisCasePolicy).
 */
class DiscardCrisisCaseRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }
}
