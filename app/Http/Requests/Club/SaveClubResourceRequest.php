<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveClubResourceRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Club;

use App\Enums\Club\ClubResourceKind;
use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\{Asset, Room};
use App\Models\Club\ClubResource;
use App\Rules\ExistsInCurrentOrganization;
use Illuminate\Validation\Rule;

class SaveClubResourceRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = ['parent_id' => ClubResource::class, 'room_id' => Room::class, 'asset_id' => Asset::class];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'name' => ['required', 'string', 'max:120'],
            'kind' => ['required', 'string', Rule::enum(ClubResourceKind::class)],
            'parent_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('club_resources')],
            'room_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('rooms')],
            'asset_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('assets')],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:999'],
            'setup_minutes' => ['nullable', 'integer', 'min:0', 'max:600'],
            'teardown_minutes' => ['nullable', 'integer', 'min:0', 'max:600'],
            'requires_clearance' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
