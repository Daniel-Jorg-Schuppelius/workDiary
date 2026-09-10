<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleReportDraftRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\Customer;
use App\Rules\ExistsInCurrentOrganization;

/**
 * Rechnungsvorschlag anlegen (Feature 152, MVP-764): Rechnungsempfänger als
 * Sqid, org-gescopt geprüft — Fehler kommen als 422 in den Dialog.
 */
class ResaleReportDraftRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'customer_id' => Customer::class,
    ];

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array {
        return [
            'customer_id' => ['required', 'integer', new ExistsInCurrentOrganization('customers')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array {
        return [
            'customer_id.required' => (string) __('resale.error.customer_required'),
            'customer_id.integer' => (string) __('resale.error.customer_required'),
        ];
    }

    public function recipient(): Customer {
        return Customer::query()->findOrFail((int) $this->validated('customer_id'));
    }
}
