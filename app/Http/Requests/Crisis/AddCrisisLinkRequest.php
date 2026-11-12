<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AddCrisisLinkRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Crisis;

use App\Http\Requests\BaseFormRequest;
use App\Services\Crisis\CrisisLinkTargets;
use Illuminate\Validation\Rule;

/**
 * Validierung für die Verknüpfung eines Fachvorgangs mit der Krisenakte
 * (MVP-218). Die Auflösung der Sqid auf das Zielobjekt (Typ-Whitelist)
 * bleibt im Controller. Berechtigung trägt der Controller.
 */
class AddCrisisLinkRequest extends BaseFormRequest {
    /** Die Auswahl des Dialogs nennt Art und Sqid in einem Feld (`art:sqid`, MVP-1605). */
    protected function prepareForValidation(): void {
        $target = $this->input('link_target');
        if (is_string($target) && str_contains($target, ':')) {
            [$type, $sqid] = explode(':', $target, 2);
            $this->merge(['linkable_type' => $type, 'linkable_sqid' => $sqid]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'linkable_type' => ['required', Rule::in(CrisisLinkTargets::keys())],
            'linkable_sqid' => ['required', 'string', 'max:64'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
