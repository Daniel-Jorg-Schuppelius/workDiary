<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DecideGroupMembershipRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Club;

use App\Http\Requests\BaseFormRequest;

/** Antrag freigeben/ablehnen (MVP-842). */
class DecideGroupMembershipRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'override' => ['sometimes', 'boolean'],
            'note' => ['nullable', 'string', 'max:255', 'required_if:override,1'],
        ];
    }

    protected function prepareForValidation(): void {
        $this->merge(['override' => $this->boolean('override')]);
    }
}
