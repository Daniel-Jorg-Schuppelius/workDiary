<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingRetentionService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing\Sepa;

use App\Enums\Invoicing\{RetentionKind, RetentionStatus};
use App\Models\Finance\IncomingInvoiceRetention;
use App\Models\Invoicing\IncomingEInvoice;
use App\Models\Platform\User;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RoundingMode;
use RuntimeException;

/**
 * Einbehalte an Eingangsrechnungen (MVP-953). Offene und freigegebene
 * Einbehalte mindern die Zahlung der Rechnung; ein freigegebener Einbehalt
 * wird im Zahllauf als eigener Posten ausgezahlt.
 */
class IncomingRetentionService {
    /**
     * @param  numeric-string|null  $percent
     * @param  numeric-string|null  $amount
     */
    public function add(IncomingEInvoice $invoice, RetentionKind $kind, ?string $percent, ?string $amount, ?CarbonImmutable $dueOn, ?string $note, User $actor): IncomingInvoiceRetention {
        if ($invoice->paid_in_run_id !== null) {
            throw new RuntimeException((string) __('sepa.retention.error.in_run'));
        }
        $gross = (string) ($invoice->amount_gross?->getAmount() ?? '0');
        if ($percent !== null) {
            $amount = bcround(bcdiv(bcmul($gross, $percent, 6), '100', 6), 2, RoundingMode::HalfAwayFromZero);
        }
        if ($amount === null || bccomp($amount, '0', 2) <= 0) {
            throw new RuntimeException((string) __('sepa.retention.error.amount'));
        }
        if (bccomp(bcadd($this->retainedAmount($invoice), $amount, 2), $gross, 2) > 0) {
            throw new RuntimeException((string) __('sepa.retention.error.exceeds'));
        }

        $retention = IncomingInvoiceRetention::query()->create([
            'organization_id' => $invoice->organization_id,
            'incoming_einvoice_id' => $invoice->id,
            'kind' => $kind,
            'percent' => $percent,
            'amount' => $amount,
            'currency' => $invoice->amount_gross?->getCurrency()->value ?? 'EUR',
            'due_on' => $dueOn?->toDateString(),
            'status' => RetentionStatus::Open,
            'note' => $note,
            'created_by' => $actor->id,
        ]);
        $retention->audit('incomingRetention.created', ['amount' => $amount, 'kind' => $kind->value]);

        return $retention;
    }

    public function release(IncomingInvoiceRetention $retention, User $actor, ?CarbonImmutable $releasedOn = null): IncomingInvoiceRetention {
        if ($retention->status !== RetentionStatus::Open) {
            throw new RuntimeException((string) __('sepa.retention.error.not_open'));
        }
        $retention->forceFill([
            'status' => RetentionStatus::Released,
            'released_on' => ($releasedOn ?? CarbonImmutable::today())->toDateString(),
            'releaser_user_id' => $actor->id,
        ])->save();
        $retention->audit('incomingRetention.released', ['amount' => (string) $retention->amount]);

        return $retention;
    }

    public function remove(IncomingInvoiceRetention $retention): void {
        if ($retention->status !== RetentionStatus::Open || $retention->incomingEInvoice?->paid_in_run_id !== null) {
            throw new RuntimeException((string) __('sepa.retention.error.locked'));
        }
        DB::transaction(function () use ($retention): void {
            $retention->audit('incomingRetention.removed', ['amount' => (string) $retention->amount]);
            $retention->delete();
        });
    }

    /**
     * Summe der Einbehalte, die die Zahlung der Rechnung mindern (offen oder freigegeben).
     *
     * @return numeric-string
     */
    public function retainedAmount(IncomingEInvoice $invoice): string {
        $sum = '0.00';
        foreach ($invoice->retentions()->whereIn('status', [RetentionStatus::Open->value, RetentionStatus::Released->value])->pluck('amount') as $amount) {
            $sum = bcadd($sum, NumberHelper::normalizeDecimalString((string) $amount), 2);
        }

        return $sum;
    }

    /** @return Collection<int, IncomingInvoiceRetention> freigegeben, noch in keinem Zahllauf */
    public function payable(): Collection {
        return IncomingInvoiceRetention::query()
            ->where('status', RetentionStatus::Released->value)
            ->whereNull('paid_in_run_id')
            ->with('incomingEInvoice')
            ->orderBy('released_on')
            ->get();
    }
}
