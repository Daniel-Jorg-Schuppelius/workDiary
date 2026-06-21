<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveForeignCustomerRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DecodesSqidInputs;

/**
 * Validierung für Fremdkunden (Endkunden). Leichtgewichtiger Kontakt; die
 * Zugehörigkeit zur Firma erfolgt über `customer_id` (als Sqid übermittelt,
 * via {@see DecodesSqidInputs} zu einer ID dekodiert).
 */
class SaveForeignCustomerRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'customer_id' => \App\Models\Customer::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'name' => ['required', 'string', 'max:200'],
            'company' => ['nullable', 'string', 'max:200'],
            'contact_name' => ['nullable', 'string', 'max:200'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'mobile' => ['nullable', 'string', 'max:64'],
            'homepage' => ['nullable', 'url', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'country' => ['nullable', 'string', 'size:2'],
            'color' => ['nullable', 'string', 'max:16'],
            'comment' => ['nullable', 'string', 'max:5000'],
        ];
    }

    protected function prepareForValidation(): void {
        $this->merge([
            'country' => $this->string('country')->upper()->value() ?: null,
        ]);
    }
}
