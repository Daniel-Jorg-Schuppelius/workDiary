<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveDisposalHandoverRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Disposal;

use App\Enums\Disposal\DisposalProofType;
use App\Http\Requests\BaseFormRequest;
use App\Rules\ExistsInCurrentOrganization;
use Illuminate\Validation\Rule;

/**
 * Entsorger-Übergabe (Feature 100): Entsorgungsfachbetrieb (ExternalContact,
 * Feature 033), Nachweistyp + Belegnummer, Datum, optionaler DMS-Beleg
 * (Upload) und EfbV-Zertifikat-Referenz.
 */
class SaveDisposalHandoverRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'external_contact_id' => ['required', 'integer', new ExistsInCurrentOrganization('external_contacts')],
            'proof_type' => ['required', Rule::enum(DisposalProofType::class)],
            'document_number' => ['required', 'string', 'max:80'],
            'handed_over_on' => ['required', 'date'],
            'certificate_reference' => ['nullable', 'string', 'max:180'],
            'note' => ['nullable', 'string', 'max:2000'],
            'proof_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }
}
