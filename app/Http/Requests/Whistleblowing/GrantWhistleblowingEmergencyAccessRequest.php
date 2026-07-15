<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GrantWhistleblowingEmergencyAccessRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Whistleblowing;

use App\Http\Requests\BaseFormRequest;

/**
 * Validierung für die Notfallfreigabe eines Hinweisgeber-Falls (auditierter
 * Grund verpflichtend). Berechtigung trägt der Controller
 * (WhistleblowingCasePolicy::grantEmergency).
 */
class GrantWhistleblowingEmergencyAccessRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'user_id' => ['required', 'integer', new \App\Rules\ExistsInCurrentOrganization()],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
