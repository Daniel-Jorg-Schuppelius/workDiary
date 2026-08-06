<?php
/*
 * Created on   : Wed Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveMaterialCostAllocationRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\{Customer, LexofficeVoucher, Project};
use App\Rules\ExistsInCurrentOrganization;
use Illuminate\Contracts\Validation\Validator;

/**
 * Materialkosten-Zuordnung an einem Kunden: entweder anteilig aus einem
 * Lexoffice-Einkaufsbeleg (voucher_id) oder als freier Betrag (dann ist eine
 * Beschreibung Pflicht). Betrag positiv, optional einem Projekt zugeordnet.
 */
class SaveMaterialCostAllocationRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'voucher_id' => LexofficeVoucher::class,
        'project_id' => Project::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'voucher_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('lexoffice_vouchers')],
            'project_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('projects')],
            'allocated_amount' => ['required', 'numeric', 'min:0.01', 'max:9999999'],
            'allocated_on' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void {
        $validator->after(function (Validator $validator): void {
            $customer = $this->route('customer');
            if (! $customer instanceof Customer) {
                return;
            }

            // Sqid-Felder liegen dekodiert in den Validierungsdaten (validationData()),
            // nicht in $this->input() (dort bleibt das rohe Sqid für den Flash-Back).
            $data = $this->validationData();
            $voucherId = $data['voucher_id'] ?? null;
            $projectId = $data['project_id'] ?? null;
            $amount = (float) ($data['allocated_amount'] ?? 0);
            $description = trim((string) ($data['description'] ?? ''));

            // Ohne Beleg muss eine Beschreibung den freien Betrag benennen.
            if (($voucherId === null || $voucherId === '') && $description === '') {
                $validator->errors()->add('description', (string) __('customer-material.error_description_required'));
            }

            if ($voucherId !== null && $voucherId !== '') {
                /** @var LexofficeVoucher|null $voucher */
                $voucher = LexofficeVoucher::query()->whereKey($voucherId)->first();
                if ($voucher === null || $voucher->voucher_type !== 'purchaseinvoice') {
                    $validator->errors()->add('voucher_id', (string) __('customer-material.error_voucher_not_purchase'));
                } elseif ($voucher->total_amount !== null && $amount > $voucher->total_amount->toFloat() + 0.001) {
                    // Ein Beleg lässt sich auf mehrere Kunden aufteilen, aber die
                    // Einzelzuordnung darf den Belegbetrag nicht übersteigen.
                    $validator->errors()->add('allocated_amount', (string) __('customer-material.error_amount_over_voucher'));
                }
            }

            if ($projectId !== null && $projectId !== '') {
                $belongs = Project::query()->whereKey($projectId)->where('customer_id', $customer->getKey())->exists();
                if (! $belongs) {
                    $validator->errors()->add('project_id', (string) __('customer-material.error_project_foreign'));
                }
            }
        });
    }
}
