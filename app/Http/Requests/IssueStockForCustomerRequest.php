<?php
/*
 * Created on   : Wed Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IssueStockForCustomerRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\{ArticleVariant, Customer, Project, Warehouse};
use App\Rules\ExistsInCurrentOrganization;
use Illuminate\Contracts\Validation\Validator;

/**
 * Lagerentnahme zugunsten eines Kunden (Materialkosten): Variante + Lagerort +
 * Menge; optional einem Projekt zugeordnet. Bewertung/Buchung übernimmt der
 * CustomerStockAllocationService (gleitender Durchschnitt).
 */
class IssueStockForCustomerRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'variant_id' => ArticleVariant::class,
        'warehouse_id' => Warehouse::class,
        'project_id' => Project::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'variant_id' => ['required', 'integer', new ExistsInCurrentOrganization('article_variants')],
            'warehouse_id' => ['required', 'integer', new ExistsInCurrentOrganization('warehouses')],
            'qty' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'project_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('projects')],
            'allocated_on' => ['nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void {
        $validator->after(function (Validator $validator): void {
            $customer = $this->route('customer');
            if (! $customer instanceof Customer) {
                return;
            }

            $data = $this->validationData();
            $projectId = $data['project_id'] ?? null;
            if ($projectId !== null && $projectId !== '') {
                $belongs = Project::query()->whereKey($projectId)->where('customer_id', $customer->getKey())->exists();
                if (! $belongs) {
                    $validator->errors()->add('project_id', (string) __('customer-material.error_project_foreign'));
                }
            }
        });
    }
}
