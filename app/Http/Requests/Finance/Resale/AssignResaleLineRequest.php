<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssignResaleLineRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Finance\Resale;

use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Http\Requests\Finance\Concerns\ChecksResaleLineRecipient;
use App\Models\Customer;
use App\Models\Reselling\ResalePeriod;
use App\Rules\ExistsInCurrentOrganization;
use App\Services\Reselling\Mirror\MirrorLine;
use App\Services\Reselling\Register\PeriodLinker;
use Illuminate\Validation\Validator;

/**
 * Zuordnung im Abgleich je Empfänger (Feature 152, Review C1/B13): Position →
 * Periode eines beliebigen Abos DIESES Empfängers; die Position gehört zu
 * einem seiner Lexoffice-Kontakte.
 */
class AssignResaleLineRequest extends BaseFormRequest {
    use ChecksResaleLineRecipient;
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'period_id' => ResalePeriod::class,
    ];

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array {
        return [
            'period_id' => ['required', 'integer', new ExistsInCurrentOrganization('resale_periods')],
            'line_id' => ['required', 'string', 'max:64'],
            'note' => ['nullable', 'string', 'max:255'],
        ] + PeriodLinker::amountRules();
    }

    public function withValidator(Validator $validator): void {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->hasAny(['period_id', 'line_id'])) {
                return;
            }
            $this->validateLineRecipient($validator, $this->period(), $this->line(), $this->customer());
        });
    }

    public function customer(): Customer {
        $customer = $this->route('customer');
        if (! $customer instanceof Customer) {
            throw new \LogicException('AssignResaleLineRequest braucht die Route {customer}.');
        }

        return $customer;
    }

    public function period(): ?ResalePeriod {
        $id = $this->validationData()['period_id'] ?? null;

        return is_numeric($id) ? ResalePeriod::query()->with('subscription.customer', 'subscription.foreignCustomer.customer')->find((int) $id) : null;
    }

    public function line(): ?MirrorLine {
        return $this->lineFrom($this->validationData()['line_id'] ?? null);
    }

    public function months(): float {
        return PeriodLinker::monthsFrom($this->validated());
    }

    public function note(): ?string {
        $note = $this->validated('note');

        return is_string($note) && $note !== '' ? $note : null;
    }
}
