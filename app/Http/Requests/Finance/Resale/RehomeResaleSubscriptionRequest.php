<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RehomeResaleSubscriptionRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Finance\Resale;

use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\Customer;
use App\Models\Reselling\ResalePeriod;
use App\Rules\ExistsInCurrentOrganization;
use Illuminate\Validation\Validator;

/**
 * Halterwechsel aus dem Abgleich (Feature 152, Review C1): das Abo der
 * Periode gehört zum Empfänger der Seite und wandert zum Zielkunden.
 */
class RehomeResaleSubscriptionRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'period_id' => ResalePeriod::class,
        'target_id' => Customer::class,
    ];

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array {
        return [
            'period_id' => ['required', 'integer', new ExistsInCurrentOrganization('resale_periods')],
            'target_id' => ['required', 'integer', new ExistsInCurrentOrganization('customers')],
        ];
    }

    public function withValidator(Validator $validator): void {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->hasAny(['period_id', 'target_id'])) {
                return;
            }
            $period = $this->period();
            if ($period === null || $period->subscription->billedTo()?->id !== $this->customer()->id) {
                $validator->errors()->add('period_id', (string) __('resale.link_error.period_foreign'));
            }
        });
    }

    public function customer(): Customer {
        $customer = $this->route('customer');
        if (! $customer instanceof Customer) {
            throw new \LogicException('RehomeResaleSubscriptionRequest braucht die Route {customer}.');
        }

        return $customer;
    }

    public function period(): ?ResalePeriod {
        $id = $this->validationData()['period_id'] ?? null;

        return is_numeric($id) ? ResalePeriod::query()->with('subscription.customer', 'subscription.foreignCustomer.customer')->find((int) $id) : null;
    }

    public function target(): Customer {
        $target = Customer::query()->find((int) $this->validated('target_id'));
        if ($target === null) {
            throw new \LogicException('Zielkunde nach Validierung nicht gefunden.');
        }

        return $target;
    }
}
