<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QuickLinkResalePeriodRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Finance\Resale;

use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Http\Requests\Finance\Concerns\ChecksResaleLineRecipient;
use App\Models\LexofficeVoucherLine;
use App\Models\Reselling\{ResalePeriod, ResaleSubscription};
use App\Rules\ExistsInCurrentOrganization;
use App\Services\Reselling\Register\PeriodLinker;
use Illuminate\Validation\Validator;

/**
 * Schnellzuordnung aus der Rechnungsliste des Abos (Feature 152, Review
 * C1/B13): Position → gewählte Periode DIESES Abos; die Position muss zum
 * Rechnungsempfänger gehören.
 */
class QuickLinkResalePeriodRequest extends BaseFormRequest {
    use ChecksResaleLineRecipient;
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'period_id' => ResalePeriod::class,
        'line_id' => LexofficeVoucherLine::class,
    ];

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array {
        return [
            'period_id' => ['required', 'integer', new ExistsInCurrentOrganization('resale_periods')],
            'line_id' => ['required', 'integer', new ExistsInCurrentOrganization('lexoffice_voucher_lines')],
        ] + PeriodLinker::amountRules();
    }

    public function withValidator(Validator $validator): void {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->hasAny(['period_id', 'line_id'])) {
                return;
            }
            $this->validateLineRecipient($validator, $this->period(), $this->line());
        });
    }

    public function subscription(): ResaleSubscription {
        $subscription = $this->route('subscription');
        if (! $subscription instanceof ResaleSubscription) {
            throw new \LogicException('QuickLinkResalePeriodRequest braucht die Route {subscription}.');
        }

        return $subscription;
    }

    /** Periode aus der Eingabe — nur eine Periode des Abos der Route. */
    public function period(): ?ResalePeriod {
        $id = $this->validationData()['period_id'] ?? null;
        if (! is_numeric($id)) {
            return null;
        }
        $period = $this->subscription()->periods()->whereKey((int) $id)->first();

        return $period?->loadMissing('subscription.customer', 'subscription.foreignCustomer.customer');
    }

    public function line(): ?LexofficeVoucherLine {
        return $this->lineFrom($this->validationData()['line_id'] ?? null);
    }

    public function months(): float {
        return PeriodLinker::monthsFrom($this->validated());
    }
}
