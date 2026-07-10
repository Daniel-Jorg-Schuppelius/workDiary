<?php
/*
 * Created on   : Tue May 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveRecurrenceRuleRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Enums\Diary\{LocationMode, Priority};
use App\Enums\Recurrence\RecurrenceFrequency;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use Illuminate\Validation\Rule;

class SaveRecurrenceRuleRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'customer_id' => \App\Models\Customer::class,
        'entry_type_id' => \App\Models\EntryType::class,
        'assigned_user_id' => \App\Models\User::class,
    ];

    protected function prepareForValidation(): void {
        foreach (['customer_id', 'entry_type_id', 'assigned_user_id', 'bymonthday', 'bymonth', 'default_service_minutes'] as $key) {
            if ($this->input($key) === '' || $this->input($key) === '0') {
                $this->merge([$key => null]);
            }
        }
        if ($this->input('default_priority') === '') {
            $this->merge(['default_priority' => null]);
        }
        if ($this->input('byweekday') === '') {
            $this->merge(['byweekday' => null]);
        }
        if (is_array($this->input('byweekday'))) {
            $this->merge(['byweekday' => implode(',', array_filter($this->input('byweekday')))]);
        }
        if ($this->input('ends_on') === '') {
            $this->merge(['ends_on' => null]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array {
        // Typ-Pflichten spiegeln (analog SaveDiaryEntryRequest): Serien-
        // Einträge entstehen ohne Formular-Validierung, deshalb muss die
        // Regel selbst den Typ-Vertrag erfüllen. Adresse/Tour kann eine
        // Regel strukturell nicht liefern → solche Typen sind für
        // Serienaufträge nicht wählbar.
        $type = $this->resolveEntryType();
        $requiresCustomer = (bool) ($type?->requires_customer);

        return [
            'name' => ['required', 'string', 'max:160'],
            'customer_id' => [$requiresCustomer ? 'required' : 'nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('customers')],
            'entry_type_id' => [
                'nullable',
                'integer',
                new \App\Rules\ExistsInCurrentOrganization('entry_types'),
                function (string $attribute, mixed $value, \Closure $fail) use ($type): void {
                    if ($type === null) {
                        return;
                    }
                    if ($type->requires_address || $type->requires_tour) {
                        $fail(__('Dieser Eintragstyp erfordert Einsatzadresse bzw. Tour und eignet sich nicht für Serienaufträge.'));
                    }
                },
            ],
            'assigned_user_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization()],

            'title_template' => ['nullable', 'string', 'max:200'],
            'content_template' => ['required', 'string', 'max:65535'],
            'default_service_minutes' => ['nullable', 'integer', 'min:0', 'max:10080'],
            'default_priority' => ['nullable', Rule::enum(Priority::class)],
            'default_location_mode' => ['required', Rule::enum(LocationMode::class)],

            'frequency' => ['required', Rule::enum(RecurrenceFrequency::class)],
            'interval' => ['required', 'integer', 'min:1', 'max:365'],
            'byweekday' => ['nullable', 'string', 'max:32'],
            'bymonthday' => ['nullable', 'integer', 'between:1,31'],
            'bymonth' => ['nullable', 'integer', 'between:1,12'],

            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    private function resolveEntryType(): ?\App\Models\EntryType {
        $raw = $this->input('entry_type_id');
        if (! $raw) {
            return null;
        }

        // rules() läuft VOR der Sqid-Dekodierung in validationData() —
        // Formulare senden Sqids (s. Memory/SaveDiaryEntryRequest).
        $id = is_string($raw) && ! ctype_digit($raw)
            ? app(\App\Services\SqidEncoder::class)->decode(\App\Models\EntryType::class, $raw)
            : (int) $raw;

        return $id ? \App\Models\EntryType::query()->find($id) : null;
    }
}
