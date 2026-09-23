<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AddExamCandidateRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Club;

use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\Club\{ClubGrade, ClubMember};
use App\Rules\ExistsInCurrentOrganization;

/** Kandidat durch die Leitung anlegen (MVP-847). */
class AddExamCandidateRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'club_member_id' => ClubMember::class,
        'target_grade_id' => ClubGrade::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'club_member_id' => ['required', 'integer', new ExistsInCurrentOrganization('club_members')],
            'target_grade_id' => ['required', 'integer', new ExistsInCurrentOrganization('club_grades')],
        ];
    }
}
