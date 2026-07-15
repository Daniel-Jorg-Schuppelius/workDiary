<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StoreCrisisCommunicationRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Crisis;

use App\Http\Requests\BaseFormRequest;
use App\Models\Crisis\CrisisCommunication;

/**
 * Validierung für einen Kommunikationsentwurf (MVP-217).
 * Berechtigung trägt der Controller (CrisisCasePolicy).
 */
class StoreCrisisCommunicationRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'audience' => ['required', 'in:' . implode(',', CrisisCommunication::AUDIENCES)],
            'subject' => ['required', 'string', 'max:300'],
            'body' => ['required', 'string', 'max:20000'],
        ];
    }
}
