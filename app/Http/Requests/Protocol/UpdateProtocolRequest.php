<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UpdateProtocolRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Protocol;

use App\Enums\Protocol\{ProtocolType, ProtocolVisibility};
use App\Http\Requests\BaseFormRequest;

/**
 * Validierung für die Teil-Aktualisierung eines Protokolls (alle Felder
 * `sometimes`). Berechtigung trägt der Controller (ProtocolPolicy).
 */
class UpdateProtocolRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:180'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'state_initial' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'state_final' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'occurred_at' => ['sometimes', 'nullable', 'date'],
            // Rule::enum statt Handliste (Vollaudit 2026-07, N48).
            'visibility' => ['sometimes', 'nullable', 'string', \Illuminate\Validation\Rule::enum(ProtocolVisibility::class)],
            'type' => ['sometimes', 'nullable', 'string', \Illuminate\Validation\Rule::enum(ProtocolType::class)],
            'tag_ids' => ['sometimes', 'nullable', 'array'],
            'tag_ids.*' => ['nullable', 'string', 'max:64'],
            'new_tags' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
