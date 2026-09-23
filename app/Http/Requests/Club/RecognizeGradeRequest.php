<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecognizeGradeRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Club;

use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\Club\ClubGrade;
use App\Rules\ExistsInCurrentOrganization;

/** Anerkennung eines vorhandenen Grades (MVP-846). */
class RecognizeGradeRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'club_grade_id' => ClubGrade::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'club_grade_id' => ['required', 'integer', new ExistsInCurrentOrganization('club_grades')],
            'obtained_on' => ['required', 'date'],
            'evidence' => ['nullable', 'string', 'max:255'],
        ];
    }
}
