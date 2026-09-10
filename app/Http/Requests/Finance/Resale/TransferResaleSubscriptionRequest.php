<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TransferResaleSubscriptionRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Finance\Resale;

use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Http\Requests\Finance\Concerns\ResolvesResaleHolder;
use App\Models\{Customer, ForeignCustomer};
use App\Models\Reselling\ResaleSubscription;
use App\Rules\ExistsInCurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Validation\{Rule, Validator};

/**
 * Lizenzabtretung (Feature 152, Review C1/B8): Teil der Lizenzen eines
 * Vertrags an einen anderen Halter. Die Menge muss über die GESAMTE Laufzeit
 * der Abtretung frei sein — nicht nur am Starttag.
 */
class TransferResaleSubscriptionRequest extends BaseFormRequest {
    use DecodesSqidInputs;
    use ResolvesResaleHolder;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'customer_id' => Customer::class,
        'foreign_customer_id' => ForeignCustomer::class,
    ];

    protected function prepareForValidation(): void {
        // Der Fremdkunden-Schritt ist bei „Kunde" nur ausgeblendet, nicht leer.
        if ($this->input('mode') !== 'foreign') {
            $this->merge(['foreign_customer_id' => null]);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array {
        return [
            'mode' => ['required', Rule::in(['customer', 'foreign'])],
            'customer_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('customers')],
            'foreign_customer_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('foreign_customers')],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'sale_unit_price' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after:starts_on'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void {
        $validator->after(function (Validator $validator): void {
            $data = $this->validationData();
            $subscription = $this->subscription();
            if ($subscription->isAssignment()) {
                $validator->errors()->add('quantity', (string) __('resale.transfer.flash.nested'));

                return;
            }
            $this->validateHolder($validator, (string) ($data['mode'] ?? 'customer'), $data);
            if ($validator->errors()->hasAny(['quantity', 'starts_on', 'ends_on'])) {
                return;
            }
            $subscription->load('assignments');
            $to = isset($data['ends_on']) && $data['ends_on'] !== '' ? CarbonImmutable::parse((string) $data['ends_on']) : $subscription->ends_on;
            $available = $subscription->quantity - $subscription->assignedQuantityBetween(CarbonImmutable::parse((string) $data['starts_on']), $to);
            if ((int) $data['quantity'] > $available) {
                $validator->errors()->add('quantity', (string) __('resale.transfer.error.quantity', ['available' => max(0, $available)]));
            }
        });
    }

    public function subscription(): ResaleSubscription {
        $subscription = $this->route('subscription');
        if (! $subscription instanceof ResaleSubscription) {
            throw new \LogicException('TransferResaleSubscriptionRequest braucht die Route {subscription}.');
        }

        return $subscription;
    }

    /**
     * Neuer Halter der Abtretung (nach der Validierung).
     *
     * @return array{customer: Customer, foreign: ForeignCustomer|null}
     */
    public function holder(): array {
        $holder = $this->resolveHolder((string) $this->validated('mode'), $this->validated());
        if ($holder['customer'] === null) {
            throw new \LogicException('Halter nach Validierung nicht auflösbar.');
        }

        return ['customer' => $holder['customer'], 'foreign' => $holder['foreign']];
    }
}
