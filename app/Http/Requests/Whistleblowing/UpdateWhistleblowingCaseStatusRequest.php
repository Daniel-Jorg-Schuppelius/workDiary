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
use App\Models\Whistleblowing\WhistleblowingCase;
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
            // Abschluss und Wiedereröffnung ohne Begründung endeten sonst als Ausnahme im Dienst (HTTP 500).
            'reason' => [Rule::requiredIf(fn (): bool => $this->target()?->isClosed() === true || $this->reopens()), 'nullable', 'string', 'max:5000'],
            'legal_hold_basis' => [Rule::requiredIf(fn (): bool => $this->target() === CaseStatus::LegalHold), 'nullable', 'string', 'max:2000'],
            'legal_hold_responsible' => [Rule::requiredIf(fn (): bool => $this->target() === CaseStatus::LegalHold), 'nullable', 'string', 'max:255'],
            'legal_hold_review_at' => [Rule::requiredIf(fn (): bool => $this->target() === CaseStatus::LegalHold), 'nullable', 'date', 'after:today'],
        ];
    }

    private function target(): ?CaseStatus {
        return CaseStatus::tryFrom((string) $this->input('to'));
    }

    private function reopens(): bool {
        $case = $this->route('case');

        return $case instanceof WhistleblowingCase
            && $case->status->isClosed()
            && $this->target() === CaseStatus::Investigating;
    }
}
