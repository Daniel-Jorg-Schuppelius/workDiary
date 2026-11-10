<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrepaymentAdvance.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting\Posting;

use App\Enums\Finance\{PostingAccountRole, PostingSourceKind};
use App\Enums\Invoicing\OnlinePaymentStatus;
use App\Models\Accounting\{AccountingEntry, AccountingPostingRule};
use App\Models\Customer\Customer;
use App\Models\Invoicing\{Invoice, OnlinePayment};
use App\Models\Platform\Organization;
use App\Services\Accounting\JournalService;
use App\Services\Invoicing\TaxResolver;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\NumberHelper;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\Model;

/**
 * Online-Vorauszahlung als erhaltene Anzahlung (MVP-1523, MVP-1542): Die
 * Zahlung bucht am Zahlungstag Anzahlung und Umsatzsteuer
 * (Mindest-Istversteuerung, § 13 Abs. 1 Nr. 1 Buchst. a S. 4 UStG); die
 * Rechnung, die sie anrechnet, löst sie mit denselben Konten und Beträgen auf,
 * ein Stornobeleg dreht diese Auflösung zurück. Beide Seiten rechnen hier,
 * damit die Steuer genau einmal entsteht.
 *
 * @phpstan-type Credit array{id: int, customer_id: int, rate: numeric-string, paid_on: string, gross: numeric-string, net: numeric-string, tax: numeric-string}
 */
final class PrepaymentAdvance {
    /** Aufgelöste Vorauszahlungen im Snapshot der Rechnungsbuchung. */
    public const string SNAPSHOT_KEY = 'prepayments';

    public function __construct(
        private readonly PostingRuleResolver $rules,
        private readonly TaxResolver $taxes,
        private readonly JournalService $journal,
    ) {}

    /**
     * Jede Vorauszahlung bucht als Anzahlung — außer ihre Rechnung ist schon
     * gebucht, ohne sie aufzulösen (Bestand vor MVP-1542): Dann gleicht die
     * Zahlung wie bisher die Forderung aus.
     */
    public function booksAsAdvance(Organization $organization, OnlinePayment $payment): bool {
        if ($payment->subject_type === null) {
            return false;
        }
        if ($payment->invoice_id === null) {
            return true;
        }
        $entry = $this->journal->activeEntryForSource($organization, PostingSourceKind::SalesInvoice->keyPrefix() . ':' . $payment->invoice_id);

        return ! $entry instanceof AccountingEntry
            || in_array((int) $payment->id, array_column($this->bookedCredits($entry), 'id'), true);
    }

    /**
     * Kunde des Gegenstands und Steuersatz am Zahlungstag; ist die Zahlung
     * schon gebucht, gilt der Satz dieser Buchung.
     *
     * @return array{customer: Customer, rate: numeric-string}|null
     */
    public function party(Organization $organization, OnlinePayment $payment): ?array {
        $subject = $payment->subject;
        $customerId = $subject instanceof Model ? $subject->getAttribute('customer_id') : null;
        $customer = is_numeric($customerId) ? Customer::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->find((int) $customerId) : null;
        if (! $customer instanceof Customer) {
            return null;
        }

        $booked = data_get($this->paymentEntry($organization, $payment)?->snapshot, 'advance_rate');
        $rate = is_numeric($booked) ? (string) $booked : $this->taxes->resolve($organization, $customer, $this->paidOn($payment))['rate'];

        return is_numeric($rate) ? ['customer' => $customer, 'rate' => NumberHelper::roundPrecise((string) $rate, 2)] : null;
    }

    /**
     * Anzahlungs- und Steuerkonto immer nach den Regeln der Online-Zahlung am
     * Zahlungstag — so trifft die Auflösung dieselben Konten wie die Zahlung.
     *
     * @return array{advance: AccountingPostingRule|null, tax: AccountingPostingRule|null}
     */
    public function rules(Organization $organization, string $rate, CarbonImmutable $paidOn): array {
        $context = ['tax_rate' => $rate];

        return [
            'advance' => $this->rules->resolve($organization, PostingSourceKind::OnlinePayment, PostingAccountRole::AdvanceReceived, $context, $paidOn),
            'tax' => $this->rules->resolve($organization, PostingSourceKind::OnlinePayment, PostingAccountRole::TaxOutput, $context, $paidOn),
        ];
    }

    /**
     * Netto und Umsatzsteuer eines Bruttobetrags.
     *
     * @param  numeric-string  $rate
     * @return array{0: numeric-string, 1: numeric-string}
     */
    public function split(Money $gross, string $rate): array {
        $net = $gross->dividedBy(NumberHelper::addPrecise('1', NumberHelper::dividePrecise($rate, '100', 6), 6));

        return [$net->getAmount(), $gross->minus($net)->getAmount()];
    }

    public function paidOn(OnlinePayment $payment): CarbonImmutable {
        return CarbonImmutable::parse($payment->paid_at ?? now())->startOfDay();
    }

    /**
     * Vorauszahlungen, die die Rechnung auflöst: zugeordnet, bezahlt,
     * abzüglich Erstattungen — ohne die, deren Zahlung schon die Forderung
     * ausgeglichen hat (Bestand vor MVP-1542).
     *
     * @return list<Credit>
     */
    public function resolvedBy(Organization $organization, Invoice $invoice): array {
        $payments = OnlinePayment::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('invoice_id', $invoice->id)
            ->whereNotNull('subject_type')
            ->whereIn('status', [OnlinePaymentStatus::Paid->value, OnlinePaymentStatus::Refunded->value])
            ->orderBy('id')
            ->get();

        $credits = [];
        foreach ($payments as $payment) {
            if (isset(((array) $this->paymentEntry($organization, $payment)?->snapshot)['settles_source_id'])) {
                continue;
            }
            $party = $this->party($organization, $payment);
            $open = $payment->gross_amount->minus($payment->refunded_amount);
            if ($party === null || ! $open->isPositive()) {
                continue;
            }

            // Wie die Zahlungsbuchung: Zahlung und Erstattung je für sich geteilt.
            [$net, $tax] = $this->split($payment->gross_amount, $party['rate']);
            [$refundNet, $refundTax] = $this->split($payment->refunded_amount, $party['rate']);
            $credits[] = [
                'id' => (int) $payment->id,
                'customer_id' => (int) $party['customer']->id,
                'rate' => $party['rate'],
                'paid_on' => $this->paidOn($payment)->toDateString(),
                'gross' => $open->getAmount(),
                'net' => NumberHelper::subtractPrecise($net, $refundNet, 2),
                'tax' => NumberHelper::subtractPrecise($tax, $refundTax, 2),
            ];
        }

        return $credits;
    }

    /**
     * Was ein Stornobeleg zurückdreht: die Auflösung der stornierten Rechnung,
     * wie sie gebucht wurde, mit umgekehrtem Vorzeichen. Ohne Buchung der
     * Rechnung war nichts aufgelöst.
     *
     * @return list<Credit>
     */
    public function reversedBy(Organization $organization, Invoice $cancellation): array {
        if ($cancellation->type !== Invoice::TYPE_CANCELLATION || $cancellation->parent_invoice_id === null) {
            return [];
        }
        $entry = $this->journal->activeEntryForSource($organization, PostingSourceKind::SalesInvoice->keyPrefix() . ':' . $cancellation->parent_invoice_id);
        if (! $entry instanceof AccountingEntry) {
            return [];
        }

        return array_map(static fn (array $credit): array => [
            ...$credit,
            'gross' => NumberHelper::negatePrecise($credit['gross']),
            'net' => NumberHelper::negatePrecise($credit['net']),
            'tax' => NumberHelper::negatePrecise($credit['tax']),
        ], $this->bookedCredits($entry));
    }

    /** @return list<Credit> */
    private function bookedCredits(AccountingEntry $entry): array {
        $stored = ((array) $entry->snapshot)[self::SNAPSHOT_KEY] ?? [];
        $credits = [];
        foreach (is_array($stored) ? $stored : [] as $credit) {
            if (! is_array($credit) || ! is_numeric($credit['id'] ?? null) || ! is_numeric($credit['customer_id'] ?? null) || ! is_string($credit['paid_on'] ?? null)
                || ! is_numeric($credit['rate'] ?? null) || ! is_numeric($credit['gross'] ?? null) || ! is_numeric($credit['net'] ?? null) || ! is_numeric($credit['tax'] ?? null)) {
                continue;
            }
            $credits[] = [
                'id' => (int) $credit['id'],
                'customer_id' => (int) $credit['customer_id'],
                'rate' => NumberHelper::roundPrecise((string) $credit['rate'], 2),
                'paid_on' => $credit['paid_on'],
                'gross' => NumberHelper::roundPrecise((string) $credit['gross'], 2),
                'net' => NumberHelper::roundPrecise((string) $credit['net'], 2),
                'tax' => NumberHelper::roundPrecise((string) $credit['tax'], 2),
            ];
        }

        return $credits;
    }

    private function paymentEntry(Organization $organization, OnlinePayment $payment): ?AccountingEntry {
        return $this->journal->activeEntryForSource($organization, PostingSourceKind::OnlinePayment->keyPrefix() . ':' . $payment->id);
    }
}
