<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MarkCrisisCommunicationSentRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Crisis;

use App\Http\Requests\BaseFormRequest;

/**
 * Validierung für die Aussendungs-Dokumentation einer freigegebenen
 * Kommunikation (MVP-217). Berechtigung trägt der Controller.
 */
class MarkCrisisCommunicationSentRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'channel' => ['required', 'string', 'max:100'],
        ];
    }
}
