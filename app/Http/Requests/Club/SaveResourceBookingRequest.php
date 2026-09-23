<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveResourceBookingRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Club;

use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\Club\{ClubMember, ClubResource};
use App\Rules\ExistsInCurrentOrganization;

/** Ressource an einen Termin belegen: Menge, optional eigenes Fenster (Ortszeit), Puffer, nutzende Person. */
class SaveResourceBookingRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = ['club_resource_id' => ClubResource::class, 'club_member_id' => ClubMember::class];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'club_resource_id' => ['required', 'integer', new ExistsInCurrentOrganization('club_resources')],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:999'],
            'starts_at' => ['nullable', 'date'],
            'ended_at' => ['prohibited'],
            'ends_at' => ['nullable', 'date', 'required_with:starts_at', 'after:starts_at'],
            'setup_minutes' => ['nullable', 'integer', 'min:0', 'max:600'],
            'teardown_minutes' => ['nullable', 'integer', 'min:0', 'max:600'],
            'club_member_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('club_members')],
            'note' => ['nullable', 'string', 'max:255'],
            'timezone' => ['nullable', 'string', 'max:64'],
        ];
    }
}
