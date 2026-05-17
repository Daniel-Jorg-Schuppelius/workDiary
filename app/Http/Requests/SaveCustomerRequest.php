<?php

/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveCustomerRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Customer|null $customer */
        $customer = $this->route('customer');

        return [
            'name' => ['required', 'string', 'max:200'],
            'number' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('customers', 'number')
                    ->where(fn ($q) => $q->where('organization_id', $customer?->organization_id))
                    ->ignore($customer?->id),
            ],
            'company' => ['nullable', 'string', 'max:200'],
            'vat_id' => ['nullable', 'string', 'max:64'],
            'contact_name' => ['nullable', 'string', 'max:200'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'mobile' => ['nullable', 'string', 'max:64'],
            'fax' => ['nullable', 'string', 'max:64'],
            'homepage' => ['nullable', 'url', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'address_street' => ['nullable', 'string', 'max:255'],
            'address_zip' => ['nullable', 'string', 'max:32'],
            'address_city' => ['nullable', 'string', 'max:128'],
            'country' => ['nullable', 'string', 'size:2'],
            'currency' => ['required', 'string', 'size:3'],
            'timezone' => ['nullable', 'string', 'max:64', 'timezone'],
            'color' => ['nullable', 'string', 'max:16'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'internal_rate' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'comment' => ['nullable', 'string', 'max:5000'],
            'invoice_text' => ['nullable', 'string', 'max:5000'],
            'billable' => ['sometimes', 'boolean'],
            'contact_persons' => ['nullable', 'array', 'max:20'],
            'contact_persons.*.name' => ['nullable', 'string', 'max:200'],
            'contact_persons.*.email' => ['nullable', 'email', 'max:255'],
            'contact_persons.*.phone' => ['nullable', 'string', 'max:64'],
            'contact_persons.*.primary' => ['nullable', 'boolean'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer'],
            'new_tags' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Leere Kontaktpersonen-Zeilen herausfiltern + primary-Flag normalisieren
        $persons = $this->input('contact_persons', []);
        if (is_array($persons)) {
            $persons = array_values(array_filter($persons, function ($row): bool {
                if (! is_array($row)) {
                    return false;
                }
                $name = trim((string) ($row['name'] ?? ''));
                $email = trim((string) ($row['email'] ?? ''));
                $phone = trim((string) ($row['phone'] ?? ''));

                return $name !== '' || $email !== '' || $phone !== '';
            }));
            $persons = array_map(static function (array $row): array {
                return [
                    'name' => $row['name'] ?? null,
                    'email' => $row['email'] ?? null,
                    'phone' => $row['phone'] ?? null,
                    'primary' => (bool) ($row['primary'] ?? false),
                ];
            }, $persons);
        } else {
            $persons = [];
        }

        $this->merge([
            'billable' => $this->boolean('billable'),
            'currency' => $this->string('currency')->upper()->value() ?: 'EUR',
            'country' => $this->string('country')->upper()->value() ?: null,
            'contact_persons' => $persons,
        ]);
    }
}
