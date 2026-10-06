<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UpdateWhistleblowingCaseStatusRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Whistleblowing;

use App\Enums\Whistleblowing\CaseStatus;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

/**
 * Validierung für den Statuswechsel eines Hinweisgeber-Falls (HinSchG).
 * Berechtigung trägt der Controller (WhistleblowingCasePolicy).
 */
class UpdateWhistleblowingCaseStatusRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            // „Gelöscht" ist kein wählbarer Status: Gelöscht wird nur über den Löschweg (Schlüsselvernichtung).
            'to' => ['required', Rule::enum(CaseStatus::class)->except([CaseStatus::Deleted])],
            // Ein Abschluss ohne Begründung endete sonst als Ausnahme im Dienst (HTTP 500).
            'reason' => [Rule::requiredIf(fn (): bool => CaseStatus::tryFrom((string) $this->input('to'))?->isClosed() === true), 'nullable', 'string', 'max:5000'],
        ];
    }
}
