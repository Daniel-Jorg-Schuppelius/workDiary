<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StoreWhistleblowingMessageRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Whistleblowing;

use App\Http\Requests\BaseFormRequest;

/**
 * Validierung für interne Notizen UND Nachrichten an die meldende Person —
 * beide Aktionen teilen dieselbe Regel (nur `body`). Berechtigung trägt der
 * Controller (WhistleblowingCasePolicy::note bzw. ::message).
 */
class StoreWhistleblowingMessageRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'body' => ['required', 'string', 'max:20000'],
        ];
    }
}
