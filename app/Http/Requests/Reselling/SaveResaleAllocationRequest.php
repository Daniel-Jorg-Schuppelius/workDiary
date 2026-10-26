<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveResaleAllocationRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Reselling;

use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Http\Requests\Finance\Concerns\ResolvesResaleHolder;
use App\Models\Customer\{Customer, ForeignCustomer};
use App\Rules\ExistsInCurrentOrganization;
use CommonToolkit\Helper\Data\StringHelper;
use Illuminate\Validation\{Rule, Validator};

/**
 * Aufteilung eines Lizenzkontos (Feature 185, MVP-1401): Produkt, fremder
 * Halter (Kunde, Fremdkunde oder eigener Bestand), Lizenzen und Gültigkeit.
 */
class SaveResaleAllocationRequest extends BaseFormRequest {
    use DecodesSqidInputs;
    use ResolvesResaleHolder;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'customer_id' => Customer::class,
        'foreign_customer_id' => ForeignCustomer::class,
    ];

    protected function prepareForValidation(): void {
        if ($this->input('mode') !== 'foreign') {
            $this->merge(['foreign_customer_id' => null]);
        }
        if ($this->input('mode') === 'own') {
            $this->merge(['customer_id' => null]);
        }
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array {
        return [
            'mode' => ['required', Rule::in(['customer', 'foreign', 'own'])],
            'customer_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('customers')],
            'foreign_customer_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('foreign_customers')],
            'product_key' => ['required', 'string', 'max:120'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'valid_from' => ['required', 'date_format:Y-m-d'],
            'valid_until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:valid_from'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void {
        $validator->after(function (Validator $validator): void {
            $data = $this->validationData();
            $this->validateHolder($validator, (string) ($data['mode'] ?? 'customer'), $data);
        });
    }

    /** @return array{customer: Customer|null, foreign: ForeignCustomer|null, own: bool} */
    public function holder(): array {
        return $this->resolveHolder((string) $this->validated('mode'), $this->validated());
    }

    public function productKey(): string {
        return (string) $this->validated('product_key');
    }

    public function note(): ?string {
        return StringHelper::trimToNull((string) ($this->validated('note') ?? ''));
    }
}
