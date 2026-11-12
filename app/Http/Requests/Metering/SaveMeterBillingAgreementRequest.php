<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveMeterBillingAgreementRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests\Metering;

use App\Enums\Metering\MeterBillingAgreementStatus;
use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Rules\ExistsInCurrentOrganization;
use Illuminate\Validation\{Rule, Validator};

/**
 * Zählerstands-Vereinbarung (Feature 116, MVP-605). Kunde/Asset/Projekt
 * kommen als Sqid und werden org-gescopt geprüft.
 */
class SaveMeterBillingAgreementRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'customer_id' => \App\Models\Customer\Customer::class,
        'asset_id' => \App\Models\Asset\Asset::class,
        'project_id' => \App\Models\Project\Project::class,
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
            'free_units' => ['nullable', 'numeric', 'min:0', 'max:99999999999.999'],
            'unit' => ['nullable', 'string', 'max:32'],
            'interval_unit' => ['required', Rule::in(['monthly', 'quarterly', 'yearly'])],
            'interval_count' => ['nullable', 'integer', 'min:1', 'max:12'],
            'next_run_on' => ['required', 'date'],
            'end_on' => ['nullable', 'date', 'after:next_run_on'],
            'status' => ['nullable', Rule::enum(MeterBillingAgreementStatus::class)],
            // Staffel als Zeilen „ab / Preis": leere Zeilen bleiben erlaubt und
            // fallen in agreementData() weg.
            'tiers' => ['nullable', 'array', 'max:20'],
            'tiers.*.from' => ['nullable', 'numeric', 'min:0', 'max:99999999999.999'],
            'tiers.*.price' => ['nullable', 'numeric', 'min:0', 'max:100000', 'required_with:tiers.*.from'],
        ];
    }

    public function withValidator(Validator $validator): void {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $tiers = $this->tierRows();
            if ($tiers === []) {
                return;
            }
            $froms = array_column($tiers, 'from');
            // Unterhalb der ersten Stufe bliebe Verbrauch unberechnet.
            if ((float) $froms[0] !== 0.0) {
                $validator->errors()->add('tiers', (string) __('metering.tiers.first_from_zero'));
            }
            if (count(array_unique(array_map(floatval(...), $froms))) !== count($froms)) {
                $validator->errors()->add('tiers', (string) __('metering.tiers.duplicate_from'));
            }
        });
    }

    /**
     * Validierte Daten mit der Staffel in Speicherform: nur gefüllte Zeilen,
     * aufsteigend nach „ab"; ohne Zeilen `null` (Einheitspreis gilt).
     *
     * @return array<string, mixed>
     */
    public function agreementData(): array {
        $data = $this->validated();
        if ($this->has('tiers')) {
            $data['tiers'] = $this->tierRows() ?: null;
        }
        if (array_key_exists('status', $data) && $data['status'] === null) {
            unset($data['status']);
        }

        return $data;
    }

    /** @return list<array{from: string, price: string}> */
    private function tierRows(): array {
        $rows = [];
        foreach ((array) $this->input('tiers', []) as $row) {
            if (! is_array($row) || ! is_numeric($row['price'] ?? null)) {
                continue;
            }
            $rows[] = ['from' => is_numeric($row['from'] ?? null) ? (string) $row['from'] : '0', 'price' => (string) $row['price']];
        }
        usort($rows, static fn (array $a, array $b): int => (float) $a['from'] <=> (float) $b['from']);

        return $rows;
    }
}
