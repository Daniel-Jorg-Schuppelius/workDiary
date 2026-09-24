<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LinkResalePeriodRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Finance\Resale;

use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Finance\Concerns\ChecksResaleLineRecipient;
use App\Models\Reselling\ResalePeriod;
use App\Services\Reselling\Mirror\MirrorLine;
use App\Services\Reselling\Register\PeriodLinker;
use Illuminate\Validation\Validator;

/**
 * Manueller Bezug aus dem Dialog (Feature 152, Review C1/B13): Position des
 * Rechnungsempfängers der Periode, Menge in Lizenzmonaten.
 */
class LinkResalePeriodRequest extends BaseFormRequest {
    use ChecksResaleLineRecipient;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array {
        return [
            'line_id' => ['required', 'string', 'max:64'],
            'months' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('line_id')) {
                return;
            }
            $this->validateLineRecipient($validator, $this->period(), $this->line());
        });
    }

    public function period(): ResalePeriod {
        $period = $this->route('period');
        if (! $period instanceof ResalePeriod) {
            throw new \LogicException('LinkResalePeriodRequest braucht die Route {period}.');
        }

        return $period->loadMissing('subscription.customer', 'subscription.foreignCustomer.customer');
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
