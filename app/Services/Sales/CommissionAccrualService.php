<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CommissionAccrualService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sales;

use App\Enums\Sales\{CommissionAssignmentSource, CommissionReversalKind, CommissionStatus};
use App\Models\Invoicing\Invoice;
use App\Models\Platform\User;
use App\Models\Sales\{CommissionAgent, CommissionRule, InvoiceCommission};
use App\Services\Invoicing\DunningService;
use App\Support\Query\DateRange;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\{Decimal, Money, Percentage};
use Illuminate\Database\Eloquent\{Builder, Collection};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Entstehung und Rueckrechnung von Provisionen (Feature 146, MVP-729).
 *
 * **Die Naht:** Eine Provision entsteht ausschliesslich am Statuswechsel der
 * Rechnung auf `paid` — derselbe Punkt in `Invoice::booted()`, an dem seit
 * MVP-718 der `invoice.paid`-Lifecycle-Webhook haengt. „Bezahlt" wird an
 * mehreren Stellen geschrieben (Bankabgleich, Kassenbuch, Retainer-Abgleich,
 * Web-Aktion); der Modell-Statuswechsel ist die einzige gemeinsame Stelle.
 * Ausgestellt-aber-offen erzeugt nie eine Provision.
 *
 * **Rueckrechnung statt Korrektur:** Storno und Gutschrift aendern die
 * urspruengliche Zeile nicht, sie erzeugen eine zweite Zeile mit negativen
 * Betraegen (`reversal_of_id`). Zwei Faelle:
 *
 *  - Die Ursprungszeile ist noch **nicht** abgerechnet: beide Zeilen gehen auf
 *    {@see CommissionStatus::Reversed} und damit in **keinen** Lauf — es wurde
 *    ja nie etwas gemeldet. Der Vorgang bleibt als Papierspur stehen.
 *  - Die Ursprungszeile steckt in einem **geschlossenen** Lauf: sie bleibt
 *    unveraendert `settled` (der Lauf ist der Beleg gegenueber der
 *    Lohnabrechnung), und die negative Zeile faellt als `pending` in den Lauf
 *    der Periode ihres Entstehungsdatums.
 *
 * Es gibt bewusst keine Auszahlung: WorkDiary rechnet und exportiert.
 */
class CommissionAccrualService {
    public function __construct(
        private readonly CommissionRuleResolver $resolver,
        private readonly DunningService $payments,
    ) {}

    /**
     * Auslöser am bezahlten Beleg. Ist der Beleg eine Gutschrift oder ein
     * Storno mit Ursprungsbeleg, mindert er die Provision des Ursprungsbelegs
     * statt eine neue zu erzeugen.
     *
     * @return list<InvoiceCommission> neu geschriebene Zeilen (leer = nichts zu tun)
     */
    public function onInvoicePaid(Invoice $invoice): array {
        if ($this->isReducingDocument($invoice)) {
            $origin = $invoice->parent;

            return $origin === null ? [] : $this->reverse(
                $origin,
                $invoice->subtotal?->abs() ?? Money::zero($invoice->currency),
                $this->dateOf($invoice),
                (string) __('commission.note.credit_note', ['number' => (string) ($invoice->number ?? $invoice->sqid)]),
                CommissionReversalKind::CreditNote,
            );
        }

        $commission = $this->accrue($invoice);

        return $commission === null ? [] : [$commission];
    }

    /**
     * Auslöser am stornierten Beleg: volle Rueckrechnung.
     *
     * @return list<InvoiceCommission>
     */
    public function onInvoiceCancelled(Invoice $invoice): array {
        // Stichtag ist der Storno, NICHT der urspruengliche Zahltag: sonst
        // fiele die Rueckrechnung in eine womoeglich laengst geschlossene
        // Periode und taeuchte in keinem Lauf mehr auf.
        $on = $invoice->cancelled_at instanceof Carbon ? $invoice->cancelled_at->copy()->startOfDay() : Carbon::today();

        return $this->reverse($invoice, null, $on, (string) __('commission.note.cancelled'), CommissionReversalKind::Cancellation);
    }

    /**
     * Auslöser an der zurückgenommenen Zahlung (MVP-989): Die Provision sinkt auf
     * den dann bezahlten Anteil, bei Regeln ohne Teilzahlung auf null. Die
     * Rückrechnung ist eine eigene Zeile; eine spätere Zahlung lässt die Provision
     * über {@see accrue()} wieder entstehen.
     *
     * @return list<InvoiceCommission>
     */
    public function onPaymentReverted(Invoice $invoice): array {
        if ($this->isReducingDocument($invoice) || $invoice->status === Invoice::STATUS_PAID) {
            return [];
        }

        return DB::transaction(function () use ($invoice): array {
            $rows = InvoiceCommission::query()->where('invoice_id', $invoice->id)->lockForUpdate()->get();
            $currency = $invoice->currency;
            $accrued = $this->accruedBase($rows, $currency);
            if (! $accrued->isPositive()) {
                return [];
            }

            $target = Money::zero($currency);
            if ($invoice->status === Invoice::STATUS_PARTIALLY_PAID) {
                $ruleId = $rows->whereNull('reversal_of_id')->sortByDesc('id')->first()?->commission_rule_id;
                $rule = $ruleId === null ? null : CommissionRule::query()->find($ruleId);
                // Gelöschte Regel: bezahlter Anteil der bisherigen Grundlage, eine Prüfung ist nicht mehr möglich.
                if ($rule === null || $rule->is_partial_accrual) {
                    $base = $rule === null ? $this->grossBase($rows, $currency) : $this->resolver->baseAmountFor($invoice, $rule);
                    $target = $base->times($this->paidShare($invoice));
                }
            }
            $excess = $accrued->minus($target);

            return $excess->isPositive()
                ? $this->reverse($invoice, $excess, Carbon::today(), (string) __('commission.note.payment_reverted'), CommissionReversalKind::Payment)
                : [];
        });
    }

    /**
     * Provision eines Belegs für die zugeordnete Person bzw. den Vermittler.
     * Bei „bezahlt“ zählt die volle Grundlage, bei Regeln mit Teilzahlung
     * (MVP-989) der bezahlte Anteil; gebucht wird nur, was über dem bereits
     * Entstandenen liegt — ein zweiter Aufruf erzeugt nichts. Satz aus der
     * Staffel, gedeckelt auf den Jahresrest (MVP-988), auszahlbar nach der
     * Haftungsfrist.
     */
    public function accrue(Invoice $invoice): ?InvoiceCommission {
        if ($this->isReducingDocument($invoice) || ! in_array($invoice->status, [Invoice::STATUS_PAID, Invoice::STATUS_PARTIALLY_PAID], true)) {
            return null;
        }

        $assignment = $this->resolver->assignmentFor($invoice);
        if ($assignment === null) {
            return null;
        }

        $earnedOn = $invoice->status === Invoice::STATUS_PAID ? $this->dateOf($invoice) : Carbon::today();
        $rule = $this->resolver->ruleFor($invoice, $assignment, $earnedOn);
        if ($rule === null) {
            return null;
        }

        $base = $this->resolver->baseAmountFor($invoice, $rule);
        if (! $base->isPositive()) {
            return null;
        }
        $target = match (true) {
            $invoice->status === Invoice::STATUS_PAID => $base,
            $rule->is_partial_accrual => $base->times($this->paidShare($invoice)),
            default => Money::zero($base->getCurrency()),
        };

        return DB::transaction(function () use ($invoice, $assignment, $rule, $base, $target, $earnedOn): ?InvoiceCommission {
            $rows = $this->recipientRows(InvoiceCommission::query()->where('invoice_id', $invoice->id), $assignment)->lockForUpdate()->get();
            $accrued = $this->accruedBase($rows, $base->getCurrency());
            $delta = Money::min($target, $base)->minus($accrued);
            if (! $delta->isPositive()) {
                return null;
            }

            $rate = $this->rateFor($rule, $assignment, $delta, $earnedOn);
            [$amount, $note] = $this->capped($rule, $assignment, $rate->amountOf($delta), $earnedOn);

            return InvoiceCommission::create([
                'organization_id' => $invoice->organization_id,
                'invoice_id' => $invoice->id,
                ...$assignment->recipient(),
                'commission_rule_id' => $rule->id,
                'assignment_source' => $assignment->source,
                'lead_id' => $assignment->source === CommissionAssignmentSource::Lead ? $assignment->lead?->id : null,
                'currency' => $invoice->currency,
                'base_amount' => $delta,
                'rate_percent' => $rate,
                'commission_amount' => $amount,
                'earned_on' => $earnedOn,
                'payable_on' => $rule->liability_days !== null && $rule->liability_days > 0 ? $earnedOn->copy()->addDays($rule->liability_days) : null,
                'status' => CommissionStatus::Pending,
                'note' => $note,
            ]);
        });
    }

    /**
     * Entstandene Grundlage: wirksame Ursprungszeilen abzüglich der Rücknahmen
     * wegen Zahlung — nur diese gibt eine spätere Zahlung wieder frei.
     *
     * @param  Collection<int, InvoiceCommission>  $rows
     */
    private function accruedBase(Collection $rows, CurrencyCode $currency): Money {
        $effective = $rows->filter(fn (InvoiceCommission $row): bool => $row->status !== CommissionStatus::Reversed);
        $amounts = [
            ...$effective->whereNull('reversal_of_id')->map(fn (InvoiceCommission $row): Money => $row->base_amount ?? Money::zero($currency))->all(),
            ...$effective->where('reversal_kind', CommissionReversalKind::Payment)->map(fn (InvoiceCommission $row): Money => ($row->base_amount ?? Money::zero($currency))->abs()->negated())->all(),
        ];

        return Money::sum($amounts, $currency);
    }

    /**
     * Summe aller wirksamen Ursprungszeilen.
     *
     * @param  Collection<int, InvoiceCommission>  $rows
     */
    private function grossBase(Collection $rows, CurrencyCode $currency): Money {
        return Money::sum($rows->filter(fn (InvoiceCommission $row): bool => $row->reversal_of_id === null && $row->status !== CommissionStatus::Reversed)
            ->map(fn (InvoiceCommission $row): Money => $row->base_amount ?? Money::zero($currency))->all(), $currency);
    }

    /**
     * Staffelsatz (MVP-988): Umsatz der Regel für diesen Empfänger im
     * Staffelzeitraum einschließlich des neuen Betrags; die höchste erreichte
     * Stufe gilt für den neuen Betrag, abgerechnete Zeilen werden nie umgedeutet.
     */
    private function rateFor(CommissionRule $rule, CommissionAssignment $assignment, Money $base, Carbon $on): Percentage {
        if ($rule->tier_period === null || $rule->currency !== $base->getCurrency()) {
            return $rule->rate_percent;
        }
        [$from, $to] = $rule->tier_period->rangeOf($on);
        $rows = $this->recipientRows(InvoiceCommission::query()->where('commission_rule_id', $rule->id), $assignment)
            ->where('currency', $base->getCurrency()->value)
            ->whereBetween('earned_on', DateRange::days($from, $to))
            ->get();
        $reached = Money::sum([$base, ...$rows->map(fn (InvoiceCommission $row): Money => $row->base_amount ?? Money::zero($base->getCurrency()))->all()], $base->getCurrency());

        $rate = $rule->rate_percent;
        foreach ($rule->orderedTiers() as $tier) {
            if ($reached->greaterThanOrEqual($tier->threshold_amount)) {
                $rate = $tier->rate_percent;
            }
        }

        return $rate;
    }

    /**
     * Jahresdeckel (MVP-988): die Provision des Kalenderjahres je Regel und
     * Empfänger übersteigt den Deckel nicht; der Rest verfällt mit Hinweis.
     *
     * @return array{0: Money, 1: string|null}
     */
    private function capped(CommissionRule $rule, CommissionAssignment $assignment, Money $amount, Carbon $on): array {
        $cap = $rule->annual_cap_amount;
        if ($cap === null || $rule->currency !== $amount->getCurrency()) {
            return [$amount, null];
        }
        $rows = $this->recipientRows(InvoiceCommission::query()->where('commission_rule_id', $rule->id), $assignment)
            ->where('currency', $amount->getCurrency()->value)
            ->whereBetween('earned_on', DateRange::days($on->copy()->startOfYear(), $on->copy()->endOfYear()))
            ->get();
        $paid = Money::sum($rows->map(fn (InvoiceCommission $row): Money => $row->commission_amount ?? Money::zero($amount->getCurrency())), $amount->getCurrency());
        $left = Money::max($cap->minus($paid), Money::zero($amount->getCurrency()));

        return $amount->greaterThan($left)
            ? [$left, (string) __('commission.note.capped', ['cap' => $cap->format()])]
            : [$amount, null];
    }

    /**
     * Anteil der Zahlungen am Rechnungsbetrag, höchstens 1.
     *
     * @return numeric-string
     */
    private function paidShare(Invoice $invoice): string {
        $total = $invoice->total;
        if ($total === null || ! $total->isPositive()) {
            return '0';
        }
        $paid = $this->payments->paidAmount($invoice);

        return $paid->greaterThanOrEqual($total) ? '1' : Decimal::of($paid->getAmount())->dividedBy(Decimal::of($total->getAmount()), 10)->getValue();
    }

    /**
     * Zeilen aller anderen Empfänger — NULL-sicher, weil Vermittlerzeilen keine
     * Person tragen und `user_id != X` sie sonst still ausließe.
     *
     * @param  Builder<InvoiceCommission>  $query
     * @return Builder<InvoiceCommission>
     */
    private function otherRecipients(Builder $query, CommissionAssignment $keep): Builder {
        $recipient = $keep->recipient();
        [$column, $id] = $recipient['user_id'] !== null ? ['user_id', $recipient['user_id']] : ['commission_agent_id', $recipient['commission_agent_id']];

        return $query->where(fn (Builder $q): Builder => $q->whereNull($column)->orWhere($column, '!=', $id));
    }

    /**
     * @param  Builder<InvoiceCommission>  $query
     * @return Builder<InvoiceCommission>
     */
    private function recipientRows(Builder $query, CommissionAssignment $assignment): Builder {
        $recipient = $assignment->recipient();

        return $recipient['user_id'] !== null
            ? $query->where('user_id', $recipient['user_id'])
            : $query->whereNull('user_id')->where('commission_agent_id', $recipient['commission_agent_id']);
    }

    /**
     * Vertriebsperson von Hand setzen (oder loesen). Bei einer bereits
     * bezahlten Rechnung wird eine noch offene Provision der bisherigen Person
     * zurueckgerechnet und fuer die neue Person neu berechnet; eine bereits
     * abgerechnete bleibt stehen und wird ueber die Rueckrechnung gemindert.
     *
     * @return list<InvoiceCommission>
     */
    public function assign(Invoice $invoice, ?User $user, ?CommissionAgent $agent = null): array {
        $invoice->sales_user_id = $user?->id;
        $invoice->sales_agent_id = $user === null ? $agent?->id : null;
        $invoice->save();

        if (! in_array($invoice->status, [Invoice::STATUS_PAID, Invoice::STATUS_PARTIALLY_PAID], true)) {
            return [];
        }

        $written = $this->reverse(
            $invoice,
            null,
            Carbon::today(),
            (string) __('commission.note.reassigned'),
            CommissionReversalKind::Reassignment,
            keep: $user !== null || $agent !== null ? new CommissionAssignment($user, CommissionAssignmentSource::Manual, null, $agent) : null,
        );

        $commission = $this->accrue($invoice->refresh());

        return $commission === null ? $written : [...$written, $commission];
    }

    /**
     * Rueckrechnung am Ursprungsbeleg.
     *
     * @param  Money|null  $limit  Bemessungsgrundlage, die zurueckgenommen wird
     *                             (`null` = vollstaendig — Storno).
     * @param  CommissionReversalKind  $kind  Anlass; nur {@see CommissionReversalKind::Payment} gibt Grundlage wieder frei.
     * @param  CommissionAssignment|null  $keep  Empfänger, dessen Zeilen stehen bleiben
     *                                         (Umzuordnung auf denselben Empfänger).
     * @return list<InvoiceCommission>
     */
    public function reverse(Invoice $invoice, ?Money $limit, Carbon $on, string $note, CommissionReversalKind $kind, ?CommissionAssignment $keep = null): array {
        return DB::transaction(function () use ($invoice, $limit, $on, $note, $kind, $keep): array {
            $query = InvoiceCommission::query()
                ->where('invoice_id', $invoice->id)
                ->whereNull('reversal_of_id')
                ->where('status', '!=', CommissionStatus::Reversed->value);
            if ($keep !== null) {
                $this->otherRecipients($query, $keep);
            }
            $originals = $query->orderBy('id')->lockForUpdate()->get();

            $written = [];
            $remainingLimit = $limit;

            foreach ($originals as $original) {
                if ($remainingLimit !== null && ! $remainingLimit->isPositive()) {
                    break;
                }

                $open = $this->unreversedBase($original);
                if (! $open->isPositive()) {
                    continue;
                }

                $take = $remainingLimit === null ? $open : Money::min($open, $remainingLimit);
                $rate = $original->rate_percent ?? Percentage::of(0);

                // Vollstaendige Rueckrechnung einer noch NICHT abgerechneten
                // Zeile: beide Zeilen fallen aus jedem Lauf heraus (gemeldet
                // wurde nie etwas), bleiben aber als Papierspur stehen.
                // Teilrueckrechnungen und alles Abgerechnete laufen dagegen als
                // offene Zeile in den naechsten Lauf und mindern ihn dort.
                $neutralize = $original->status === CommissionStatus::Pending
                    && $original->settlement_run_id === null
                    && $take->equals($original->base_amount ?? Money::zero($original->currency));

                $reversal = InvoiceCommission::create([
                    'organization_id' => $original->organization_id,
                    'invoice_id' => $original->invoice_id,
                    'user_id' => $original->user_id,
                    'commission_agent_id' => $original->commission_agent_id,
                    'commission_rule_id' => $original->commission_rule_id,
                    'assignment_source' => $original->assignment_source,
                    'lead_id' => $original->lead_id,
                    'currency' => $original->currency,
                    'base_amount' => $take->negated(),
                    'rate_percent' => $rate,
                    'commission_amount' => $this->reversedCommission($original, $rate, $take)->negated(),
                    'earned_on' => $on,
                    'status' => $neutralize ? CommissionStatus::Reversed : CommissionStatus::Pending,
                    'reversal_of_id' => $original->id,
                    'reversal_kind' => $kind,
                    'note' => $note,
                ]);

                if ($neutralize) {
                    $original->status = CommissionStatus::Reversed;
                    $original->save();
                }

                if ($remainingLimit !== null) {
                    $remainingLimit = $remainingLimit->minus($take);
                }
                $written[] = $reversal;
            }

            return $written;
        });
    }

    /**
     * Zurückzurechnende Provision: Satz × Betrag; war die Zeile gedeckelt
     * (MVP-988), anteilig aus ihrer tatsächlichen Provision.
     */
    private function reversedCommission(InvoiceCommission $original, Percentage $rate, Money $take): Money {
        $base = $original->base_amount ?? Money::zero($original->currency);
        $commission = $original->commission_amount ?? Money::zero($original->currency);
        if ($rate->amountOf($base)->equals($commission) || ! $base->isPositive()) {
            return $rate->amountOf($take);
        }

        return $take->equals($base) ? $commission : $commission->times(Decimal::of($take->getAmount())->dividedBy(Decimal::of($base->getAmount()), 10)->getValue());
    }

    /** Noch nicht zurueckgerechnete Bemessungsgrundlage einer Zeile. */
    private function unreversedBase(InvoiceCommission $original): Money {
        $currency = $original->currency;
        $base = $original->base_amount ?? Money::zero($currency);

        $reversed = InvoiceCommission::query()
            ->where('reversal_of_id', $original->id)
            ->get()
            ->map(fn (InvoiceCommission $row): Money => ($row->base_amount ?? Money::zero($currency))->abs());

        // Bewusst kein SQL-SUM: auf SQLite laufen Summen ueber decimal-Spalten
        // durch float. Money::sum rechnet exakt (bc).
        $already = $reversed->isEmpty() ? Money::zero($currency) : Money::sum($reversed, $currency);

        return Money::max($base->minus($already), Money::zero($currency));
    }

    /** Gutschrift/Storno-Beleg mit Ursprungsbeleg? */
    private function isReducingDocument(Invoice $invoice): bool {
        return in_array($invoice->type, [Invoice::TYPE_CREDIT_NOTE, Invoice::TYPE_CANCELLATION], true);
    }

    /** Stichtag der Periodenzuordnung: der Zahltag des Belegs. */
    private function dateOf(Invoice $invoice): Carbon {
        $date = $invoice->paid_on ?? $invoice->issued_on;

        return $date instanceof Carbon ? $date->copy()->startOfDay() : Carbon::today();
    }
}
