<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ChangeClubMembershipKindRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Club;

use App\Enums\Club\ClubMembershipKind;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

/** Art-Wechsel bzw. Pause zum Stichtag (MVP-842). */
class ChangeClubMembershipKindRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'kind' => ['required', 'string', Rule::enum(ClubMembershipKind::class)],
            'effective_on' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
