<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssignResaleHolderRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Finance\Resale;

use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Http\Requests\Finance\Concerns\ResolvesResaleHolder;
use App\Models\{Customer, ForeignCustomer};
use App\Rules\ExistsInCurrentOrganization;
use Illuminate\Validation\{Rule, Validator};

/**
 * Inbox-Zuordnung (Feature 152, Review C1): eine Firma laut Anbieter wird
 * Kunde, Endkunde eines Partners, vorhandener Fremdkunde oder eigener Bestand.
 */
class AssignResaleHolderRequest extends BaseFormRequest {
    use DecodesSqidInputs;
    use ResolvesResaleHolder;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'customer_id' => Customer::class,
        'foreign_customer_id' => ForeignCustomer::class,
    ];

    protected function prepareForValidation(): void {
        // Ausgeblendete Schritte des Dialogs tragen keine Entscheidung.
        $mode = (string) $this->input('mode');
        $this->merge([
            'customer_id' => in_array($mode, ['customer', 'partner'], true) ? $this->input('customer_id') : null,
            'foreign_customer_id' => $mode === 'foreign' ? $this->input('foreign_customer_id') : null,
        ]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array {
        return [
            'company' => ['required', 'string', 'max:190'],
            'mode' => ['required', Rule::in(['customer', 'partner', 'foreign', 'own'])],
            'customer_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('customers')],
            'foreign_customer_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('foreign_customers')],
        ];
    }

    public function withValidator(Validator $validator): void {
        $validator->after(function (Validator $validator): void {
            $data = $this->validationData();
            $this->validateHolder($validator, (string) ($data['mode'] ?? ''), $data);
        });
    }

    public function mode(): string {
        return (string) $this->validated('mode');
    }

    public function company(): string {
        return trim((string) $this->validated('company'));
    }

    /**
     * @return array{customer: Customer|null, foreign: ForeignCustomer|null, own: bool}
     */
    public function holder(): array {
        return $this->resolveHolder($this->mode(), $this->validated());
    }
}
