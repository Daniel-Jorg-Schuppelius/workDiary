<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveMeterBillingAgreementRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Rules\ExistsInCurrentOrganization;
use Illuminate\Validation\Rule;

/**
 * Zählerstands-Vereinbarung (Feature 116, MVP-605). Kunde/Asset/Projekt
 * kommen als Sqid und werden org-gescopt geprüft.
 */
class SaveMeterBillingAgreementRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'customer_id' => \App\Models\Customer::class,
        'asset_id' => \App\Models\Asset::class,
        'project_id' => \App\Models\Project::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'customer_id' => ['required', 'integer', new ExistsInCurrentOrganization('customers')],
            'asset_id' => ['required', 'integer', new ExistsInCurrentOrganization('assets')],
            'project_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('projects')],
            'title' => ['required', 'string', 'max:191'],
            'base_price' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'unit_price' => ['required', 'numeric', 'min:0', 'max:100000'],
            'free_units' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:32'],
            'interval_unit' => ['required', Rule::in(['monthly', 'quarterly', 'yearly'])],
            'interval_count' => ['nullable', 'integer', 'min:1', 'max:12'],
            'next_run_on' => ['required', 'date'],
            'end_on' => ['nullable', 'date', 'after:next_run_on'],
            'status' => ['nullable', Rule::in(['active', 'paused', 'ended'])],
            // Staffel als Zeilen „ab;Preis" — die JSON-Form entsteht daraus,
            // damit niemand JSON in ein Formular tippen muss.
            'tiers' => ['nullable', 'array'],
            'tiers.*.from' => ['nullable', 'numeric', 'min:0'],
            'tiers.*.price' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
