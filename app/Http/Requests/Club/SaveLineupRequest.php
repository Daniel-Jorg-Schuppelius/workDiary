<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveLineupRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Club;

use App\Enums\Club\ClubLineupSlot;
use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\Club\ClubMember;
use Illuminate\Validation\Rule;

/** Aufstellung als parallele Zeilenfelder (Mitglied, Platz, Position, Trikot, Paarung, Reihenfolge). */
class SaveLineupRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = ['lineup_member' => ClubMember::class];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'lineup_member' => ['required', 'array', 'max:60'],
            'lineup_member.*' => ['required', 'integer'],
            'lineup_slot' => ['required', 'array'],
            'lineup_slot.*' => ['nullable', 'string', Rule::enum(ClubLineupSlot::class)],
            'lineup_position' => ['nullable', 'array'],
            'lineup_position.*' => ['nullable', 'string', 'max:30'],
            'lineup_jersey' => ['nullable', 'array'],
            'lineup_jersey.*' => ['nullable', 'integer', 'min:0', 'max:999'],
            'lineup_pairing' => ['nullable', 'array'],
            'lineup_pairing.*' => ['nullable', 'integer', 'min:1', 'max:20'],
            'lineup_order' => ['nullable', 'array'],
            'lineup_order.*' => ['nullable', 'integer', 'min:0', 'max:999'],
            'release' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return list<array{club_member_id: int, slot: string|null, position_code: string|null, jersey_no: int|string|null, pairing_no: int|string|null, order_no: int|string|null}>
     */
    public function rows(): array {
        $data = $this->validated();
        $rows = [];
        foreach ((array) $data['lineup_member'] as $index => $memberId) {
            $rows[] = [
                'club_member_id' => (int) $memberId,
                'slot' => $data['lineup_slot'][$index] ?? null,
                'position_code' => $data['lineup_position'][$index] ?? null,
                'jersey_no' => $data['lineup_jersey'][$index] ?? null,
                'pairing_no' => $data['lineup_pairing'][$index] ?? null,
                'order_no' => $data['lineup_order'][$index] ?? null,
            ];
        }

        return $rows;
    }
}
