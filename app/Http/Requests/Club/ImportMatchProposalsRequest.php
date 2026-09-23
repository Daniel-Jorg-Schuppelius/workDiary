<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ImportMatchProposalsRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Club;

use App\Enums\Club\ClubMatchProposalSource;
use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\Club\ClubGroup;
use App\Rules\ExistsInCurrentOrganization;
use Illuminate\Validation\Rule;

/** Spielplan-Import als Datei (CSV/ICS) oder eingefügter Text. */
class ImportMatchProposalsRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = ['club_group_id' => ClubGroup::class];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'club_group_id' => ['required', 'integer', new ExistsInCurrentOrganization('club_groups')],
            'source' => ['required', 'string', Rule::enum(ClubMatchProposalSource::class)],
            'file' => ['nullable', 'file', 'max:2048', 'mimes:csv,txt,ics,ical'],
            'content' => ['nullable', 'string', 'max:200000', 'required_without:file'],
            'timezone' => ['nullable', 'string', 'max:64'],
        ];
    }
}
