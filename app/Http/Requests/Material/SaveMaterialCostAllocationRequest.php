<?php
/*
 * Created on   : Wed Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveMaterialCostAllocationRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests\Material;

use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\Customer\Customer;
use App\Models\Project\Project;
use App\Rules\ExistsInCurrentOrganization;
use App\Services\Billing\Purchase\{PurchaseDocument, PurchaseDocuments};
use Illuminate\Contracts\Validation\Validator;

/**
 * Materialkosten-Zuordnung an einem Kunden: entweder anteilig aus einem
 * Eingangsbeleg der Einkaufsbeleg-Registry (`document`, MVP-1036: Lexoffice,
 * Ausgaben, Eingangs-E-Rechnungen) oder als freier Betrag (dann ist eine
 * Beschreibung Pflicht). Betrag positiv, optional einem Projekt zugeordnet.
 */
class SaveMaterialCostAllocationRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'project_id' => Project::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            // Formularschlüssel eines Eingangsbelegs aus der Einkaufsbeleg-Registry (MVP-1036).
            'document' => ['nullable', 'string', 'max:120'],
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
            $hasDocument = trim((string) ($data['document'] ?? '')) !== '';
            $projectId = $data['project_id'] ?? null;
            $amount = (float) ($data['allocated_amount'] ?? 0);
            $description = trim((string) ($data['description'] ?? ''));

            // Ohne Beleg muss eine Beschreibung den freien Betrag benennen.
            if (! $hasDocument && $description === '') {
                $validator->errors()->add('description', (string) __('customer-material.error_description_required'));
            }

            if ($hasDocument) {
                $document = $this->purchaseDocument();
                if ($document === null || $document->credit) {
                    $validator->errors()->add('document', (string) __('customer-material.error_voucher_not_purchase'));
                } elseif ($amount > $document->net->toFloat() + 0.001) {
                    // Ein Beleg lässt sich auf mehrere Kunden aufteilen, aber die
                    // Einzelzuordnung darf den Nettobetrag des Belegs nicht übersteigen.
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

    /** Gewählter, zuteilbarer Eingangsbeleg der Organisation des Kunden, sonst null. */
    public function purchaseDocument(): ?PurchaseDocument {
        $customer = $this->route('customer');
        $key = trim((string) $this->input('document', ''));
        $organization = $customer instanceof Customer ? $customer->organization()->first() : null;

        return $organization !== null && $key !== '' ? app(PurchaseDocuments::class)->byKey($organization, $key) : null;
    }
}
