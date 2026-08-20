<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveDisposalJobRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Disposal;

use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Rules\ExistsInCurrentOrganization;

/**
 * Kopf der Entsorgungsakte (Feature 100): Kunde, Abholort, Auftragsbezug,
 * Verantwortliche, Abholdatum. Autorisierung über die Policy im Controller.
 */
class SaveDisposalJobRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /**
     * Sqid-Eingaben (Audit 2026-08, W3.3): Auswahllisten liefern Sqids; der
     * Trait dekodiert sie in validationData(), die Regeln pruefen danach
     * regulaer die numerische ID gegen die eigene Organisation.
     *
     * @var array<string, class-string>
     */
    protected array $sqidFields = [
        'customer_id' => \App\Models\Customer::class,
        'diary_entry_id' => \App\Models\DiaryEntry::class,
        'responsible_user_id' => \App\Models\User::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'customer_id' => ['required', 'integer', new ExistsInCurrentOrganization('customers')],
            'site_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('sites')],
            'diary_entry_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('diary_entries')],
            'responsible_user_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('users')],
            'picked_up_on' => ['nullable', 'date'],
            'total_weight_kg' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
