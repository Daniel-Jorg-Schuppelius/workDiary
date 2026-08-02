<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SignDisposalJobRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Disposal;

use App\Http\Requests\BaseFormRequest;

/**
 * Übernahme-Unterschrift des Kunden (Feature 100): Name + Canvas-PNG
 * (Signature-Pad, Muster Timesheet). Größen-/PNG-Prüfung im Service.
 */
class SignDisposalJobRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'signer_name' => ['required', 'string', 'min:2', 'max:120'],
            'signature' => ['required', 'string', 'max:2000000'],
        ];
    }
}
