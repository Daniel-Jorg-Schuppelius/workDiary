<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveFeeAssignmentRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Club;

use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\Club\{ClubFeeTariff, ClubMember};
use App\Rules\ExistsInCurrentOrganization;

/** Zuordnung Mitglied → Konto/Tarif (MVP-849). */
class SaveFeeAssignmentRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'club_member_id' => ClubMember::class,
        'club_fee_tariff_id' => ClubFeeTariff::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'club_member_id' => ['required', 'integer', new ExistsInCurrentOrganization('club_members')],
            'club_fee_tariff_id' => ['required', 'integer', new ExistsInCurrentOrganization('club_fee_tariffs')],
            'valid_from' => ['required', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
