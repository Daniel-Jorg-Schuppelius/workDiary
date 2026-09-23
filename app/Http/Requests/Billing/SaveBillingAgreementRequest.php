<?php
/*
 * Created on   : Thu Jul 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveBillingAgreementRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Enums\Billing\{BillingAgreementMode, BillingRateDayType};
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Rules\ExistsInCurrentOrganization;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Validation\Rule;

/**
 * Sonderkonditions-Profil eines Kunden (Feature 098). Satzzeilen kommen als
 * parallele Arrays (rate_*[]) — Zeilen ohne Stundensatz werden ignoriert.
 */
class SaveBillingAgreementRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'rate_activity_category_id' => \App\Models\ActivityCategory::class,
        'travel_categories' => \App\Models\ActivityCategory::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'mode' => ['required', Rule::enum(BillingAgreementMode::class)],
            'currency' => ['required', Rule::enum(CurrencyCode::class)],
            'expected_monthly_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'workdays_per_week' => ['required', 'integer', 'min:1', 'max:7'],
            // Anfahrt je Zeiteintrag; 480 Min. als Plausibilitätsdeckel.
            'travel_minutes_per_entry' => ['nullable', 'integer', 'min:0', 'max:480'],
            'travel_categories' => ['array'],
            'travel_categories.*' => ['integer', new ExistsInCurrentOrganization('activity_categories', includeGlobal: true)],
            'holidays_as_weekend' => ['sometimes', 'boolean'],
            'opening_balance' => ['nullable', 'numeric', 'min:-9999999', 'max:9999999'],
            'opening_balance_date' => ['nullable', 'date'],
            'active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'rate_activity_category_id' => ['array'],
            'rate_activity_category_id.*' => ['nullable', 'integer', new ExistsInCurrentOrganization('activity_categories', includeGlobal: true)],
            'rate_day_type' => ['array'],
            'rate_day_type.*' => ['required', Rule::enum(BillingRateDayType::class)],
            'rate_hourly_rate' => ['array'],
            'rate_hourly_rate.*' => ['nullable', 'numeric', 'min:0', 'max:99999'],
        ];
    }

    /**
     * Retainer-Modus (Feature 098) setzt Lexoffice-Rechnungshoheit voraus und
     * braucht einen echten Pauschalbetrag — sonst gäbe es „doppelte Hoheit"
     * (lokaler Saldo ohne führendes Buchhaltungsprogramm).
     */
    public function withValidator(\Illuminate\Contracts\Validation\Validator $validator): void {
        $validator->after(function (\Illuminate\Contracts\Validation\Validator $validator): void {
            if ($this->input('mode') !== BillingAgreementMode::Retainer->value) {
                return;
            }

            $customer = $this->route('customer');
            if ($customer instanceof \App\Models\Customer) {
                $mode = app(\App\Services\Finance\BillingModeResolver::class)->effectiveFor($customer);
                if ($mode !== \App\Enums\Finance\BillingMode::Lexoffice) {
                    $validator->errors()->add('mode', (string) __('customer-billing.retainer_requires_lexoffice'));
                }
            }

            if ((float) $this->input('expected_monthly_amount', 0) <= 0) {
                $validator->errors()->add('expected_monthly_amount', (string) __('customer-billing.retainer_amount_required'));
            }
        });
    }

    /**
     * Eingereichte Satzzeilen (ohne Leerzeilen), index-aligniert aus den
     * parallelen Arrays zusammengesetzt.
     *
     * @return list<array{activity_category_id: int|null, day_type: string, hourly_rate: float}>
     */
    public function rateRows(): array {
        $categories = (array) $this->validated('rate_activity_category_id', []);
        $dayTypes = (array) $this->validated('rate_day_type', []);
        $rates = (array) $this->validated('rate_hourly_rate', []);

        $rows = [];
        foreach ($rates as $index => $hourly) {
            if ($hourly === null || $hourly === '') {
                continue;
            }
            $rows[] = [
                'activity_category_id' => $categories[$index] ?? null,
                'day_type' => (string) ($dayTypes[$index] ?? BillingRateDayType::Weekday->value),
                'hourly_rate' => (float) $hourly,
            ];
        }

        return $rows;
    }
}
