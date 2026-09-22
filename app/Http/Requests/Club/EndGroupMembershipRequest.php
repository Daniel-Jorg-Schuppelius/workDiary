<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EndGroupMembershipRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Club;

use App\Http\Requests\BaseFormRequest;

/** Gruppenzuordnung beenden (MVP-842): letzter gültiger Tag. */
class EndGroupMembershipRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'valid_to' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
