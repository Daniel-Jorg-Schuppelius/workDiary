<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FillProtocolItemRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Protocol;

use App\Http\Requests\BaseFormRequest;

/**
 * Validierung für das Befüllen einer Protokollposition (Ergebnis/Notiz/
 * strukturierter Wert). Die typabhängige Wertprüfung übernimmt der
 * ProtocolService. Berechtigung trägt der Controller (ProtocolPolicy).
 */
class FillProtocolItemRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'result' => ['nullable', 'string', 'max:20'],
            'note' => ['nullable', 'string', 'max:5000'],
            'value_json' => ['nullable', 'array'],
        ];
    }
}
