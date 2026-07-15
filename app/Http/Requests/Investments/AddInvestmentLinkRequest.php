<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AddInvestmentLinkRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Investments;

use App\Http\Requests\BaseFormRequest;

/**
 * Validierung für die Verknüpfung eines Folgeobjekts mit der
 * Investitionsakte (MVP-204). Die Auflösung der Sqid auf das Zielobjekt
 * (Typ-Whitelist) bleibt im Controller. Berechtigung trägt der Controller.
 */
class AddInvestmentLinkRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'linkable_type' => ['required', 'in:project,purchase_order,asset,incoming_einvoice,document'],
            'linkable_sqid' => ['required', 'string', 'max:64'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
