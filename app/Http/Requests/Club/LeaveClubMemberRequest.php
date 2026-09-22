<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LeaveClubMemberRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Club;

use App\Http\Requests\BaseFormRequest;

/** Austritt erfassen (MVP-842): Austrittstag ist der letzte Mitgliedstag. */
class LeaveClubMemberRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'left_on' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
