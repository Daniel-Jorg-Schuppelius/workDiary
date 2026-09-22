<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CancelClubEventRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Club;

use App\Http\Requests\BaseFormRequest;

/** Termin absagen (MVP-843), wahlweise mit allen folgenden Vorkommen der Serie. */
class CancelClubEventRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'cancel_reason' => ['nullable', 'string', 'max:500'],
            'scope' => ['nullable', 'string', 'in:this,future'],
        ];
    }
}
