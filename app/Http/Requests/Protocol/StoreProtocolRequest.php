<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StoreProtocolRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Protocol;

use App\Enums\Protocol\{ProtocolType, ProtocolVisibility};
use App\Http\Controllers\ProtocolController;
use App\Http\Requests\BaseFormRequest;

/**
 * Validierung für das Anlegen eines Protokolls. Die Auflösung des Subjekts
 * (Typ-Whitelist {@see ProtocolController::SUBJECT_MAP} + Existenz) und die
 * Tag-Synchronisation bleiben im Controller. Berechtigung trägt der
 * Controller (ProtocolPolicy).
 */
class StoreProtocolRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'subject_kind' => ['required', 'string', 'in:' . implode(',', array_keys(ProtocolController::SUBJECT_MAP))],
            'subject_id' => ['required', 'integer', 'min:1'],
            // Rule::enum statt Handliste (Vollaudit 2026-07, N48).
            'type' => ['required', 'string', \Illuminate\Validation\Rule::enum(ProtocolType::class)],
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:10000'],
            'state_initial' => ['nullable', 'string', 'max:10000'],
            'occurred_at' => ['nullable', 'date'],
            'visibility' => ['nullable', 'string', \Illuminate\Validation\Rule::enum(ProtocolVisibility::class)],
            'template_id' => ['nullable', 'integer', 'min:1'],
            'template_version' => ['nullable', 'integer', 'min:1'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['nullable', 'string', 'max:64'],
            'new_tags' => ['nullable', 'string', 'max:500'],
        ];
    }
}
