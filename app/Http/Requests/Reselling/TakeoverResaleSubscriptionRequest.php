<?php
/*
 * Created on   : Sat Oct 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TakeoverResaleSubscriptionRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Reselling;

use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Http\Requests\Finance\Concerns\ResolvesResaleHolder;
use App\Models\Customer\{Customer, ForeignCustomer};
use App\Models\Reselling\ResaleSubscription;
use App\Rules\ExistsInCurrentOrganization;
use App\Services\Reselling\Register\HolderTakeover;
use Carbon\CarbonImmutable;
use Illuminate\Validation\{Rule, Validator};

/**
 * Übernahme durch eine andere Firma ab Stichtag (Phase 166, MVP-1451).
 */
class TakeoverResaleSubscriptionRequest extends BaseFormRequest {
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
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'waive_before' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void {
        $validator->after(function (Validator $validator): void {
            $data = $this->validationData();
            $subscription = $this->subscription();
            if ($subscription->is_own_holding || ! $subscription->hasHolder()) {
                $validator->errors()->add('customer_id', (string) __('resale.takeover.error.no_holder'));

                return;
            }
            $this->validateHolder($validator, (string) ($data['mode'] ?? 'customer'), $data);
            if ($validator->errors()->hasAny(['customer_id', 'foreign_customer_id', 'starts_on'])) {
                return;
            }
            $holder = $this->resolveHolder((string) $data['mode'], $data);
            $foreignId = $holder['foreign']?->id;
            $customerId = $foreignId === null ? $holder['customer']?->id : null;
            if ($customerId === $subscription->customer_id && $foreignId === $subscription->foreign_customer_id) {
                $validator->errors()->add('customer_id', (string) __('resale.takeover.error.same_holder'));

                return;
            }
            $from = CarbonImmutable::parse((string) $data['starts_on']);
            if ($subscription->ends_on !== null && $from->greaterThan($subscription->ends_on)) {
                $validator->errors()->add('starts_on', (string) __('resale.takeover.error.after_end', ['date' => $subscription->ends_on->format('d.m.Y')]));

                return;
            }
            // Ganzer Wechsel nur ohne berechnete Perioden des bisherigen Halters (Sicherheitsaudit 2026-11-02, sec-06).
            if (HolderTakeover::billedBeforeWhole($subscription, $from)) {
                $validator->errors()->add('starts_on', (string) __('resale.takeover.error.billed_whole', ['holder' => $subscription->holderLabel(), 'date' => HolderTakeover::defaultFrom($subscription)->format('d.m.Y')]));

                return;
            }
            if (HolderTakeover::quantityFrom($subscription, $from) <= 0) {
                $validator->errors()->add('starts_on', (string) __('resale.takeover.error.nothing_free'));
            }
        });
    }

    public function subscription(): ResaleSubscription {
        $subscription = $this->route('subscription');
        if (! $subscription instanceof ResaleSubscription) {
            throw new \LogicException('TakeoverResaleSubscriptionRequest braucht die Route {subscription}.');
        }

        return $subscription;
    }

    public function takeoverDate(): CarbonImmutable {
        return CarbonImmutable::parse((string) $this->validated('starts_on'));
    }

    /**
     * Neuer Halter (nach der Validierung).
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
