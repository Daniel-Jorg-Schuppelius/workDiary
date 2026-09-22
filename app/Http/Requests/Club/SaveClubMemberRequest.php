<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveClubMemberRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Club;

use App\Enums\Club\ClubMembershipKind;
use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\Club\ClubMember;
use App\Models\{Organization, User};
use App\Rules\ExistsInCurrentOrganization;
use Illuminate\Validation\Rule;

/**
 * Stammdaten eines Vereinsmitglieds (MVP-842). Art und Eintritt nur beim
 * Anlegen — danach laufen sie über den Mitgliedschaftsverlauf.
 */
class SaveClubMemberRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'user_id' => User::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        $member = $this->route('member');
        $member = $member instanceof ClubMember ? $member : null;
        $organizationId = $member !== null ? $member->organization_id : $this->currentOrganizationId();
        $isCreate = $member === null;

        return [
            'member_no' => [
                'nullable',
                'integer',
                'min:1',
                'max:999999999',
                Rule::unique('club_members', 'member_no')
                    ->where(fn($query) => $query->where('organization_id', $organizationId))
                    ->ignore($member?->id),
            ],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:60'],
            'street' => ['nullable', 'string', 'max:190'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:120'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'kind' => $isCreate ? ['required', 'string', Rule::enum(ClubMembershipKind::class)] : ['prohibited'],
            'joined_on' => $isCreate ? ['required', 'date'] : ['prohibited'],
            'user_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('users')],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /** Organisationskontext der Anfrage — abgesichert, weil die Bindung in Konsole/Queue fehlen kann. */
    private function currentOrganizationId(): ?int {
        if (app()->bound('currentOrganization')) {
            $organization = app('currentOrganization');
            if ($organization instanceof Organization) {
                return (int) $organization->id;
            }
        }

        return null;
    }
}
