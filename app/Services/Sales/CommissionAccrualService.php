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

use App\Enums\Sales\{CommissionAssignmentSource, CommissionStatus};
use App\Models\{Invoice, User};
use App\Models\Sales\InvoiceCommission;
use CommonToolkit\ValueObjects\{Money, Percentage};
use Illuminate\Database\Eloquent\Builder;
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
    public function __construct(private readonly CommissionRuleResolver $resolver) {}

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

        return $this->reverse($invoice, null, $on, (string) __('commission.note.cancelled'));
    }

    /**
     * Provision fuer eine bezahlte Rechnung berechnen und schreiben.
     * Idempotent: eine bestehende, nicht zurueckgerechnete Zeile fuer dieselbe
     * Person bleibt stehen (der Statuswechsel auf `paid` kann mehrfach
     * geschrieben werden).
     */
    public function accrue(Invoice $invoice): ?InvoiceCommission {
        if ($invoice->status !== Invoice::STATUS_PAID || $this->isReducingDocument($invoice)) {
            return null;
        }

        $assignment = $this->resolver->assignmentFor($invoice);
        if ($assignment === null) {
            return null;
        }

        $earnedOn = $this->dateOf($invoice);
        $rule = $this->resolver->ruleFor($invoice, $assignment, $earnedOn);
        if ($rule === null) {
            return null;
        }

        $base = $this->resolver->baseAmountFor($invoice, $rule);
        if (! $base->isPositive()) {
            return null;
        }

        return DB::transaction(function () use ($invoice, $assignment, $rule, $base, $earnedOn): InvoiceCommission {
            $existing = InvoiceCommission::query()
                ->where('invoice_id', $invoice->id)
                ->where('user_id', $assignment->user->id)
                ->whereNull('reversal_of_id')
                ->where('status', '!=', CommissionStatus::Reversed->value)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof InvoiceCommission) {
                return $existing;
            }

            return InvoiceCommission::create([
                'organization_id' => $invoice->organization_id,
                'invoice_id' => $invoice->id,
                'user_id' => $assignment->user->id,
                'commission_rule_id' => $rule->id,
                'assignment_source' => $assignment->source,
                'lead_id' => $assignment->source === CommissionAssignmentSource::Lead ? $assignment->lead?->id : null,
                'currency' => $invoice->currency,
                'base_amount' => $base,
                'rate_percent' => $rule->rate_percent,
                'commission_amount' => $rule->rate_percent->amountOf($base),
                'earned_on' => $earnedOn,
                'status' => CommissionStatus::Pending,
            ]);
        });
    }

    /**
     * Vertriebsperson von Hand setzen (oder loesen). Bei einer bereits
     * bezahlten Rechnung wird eine noch offene Provision der bisherigen Person
     * zurueckgerechnet und fuer die neue Person neu berechnet; eine bereits
     * abgerechnete bleibt stehen und wird ueber die Rueckrechnung gemindert.
     *
     * @return list<InvoiceCommission>
     */
    public function assign(Invoice $invoice, ?User $user): array {
        $invoice->sales_user_id = $user?->id;
        $invoice->save();

        if ($invoice->status !== Invoice::STATUS_PAID) {
            return [];
        }

        $written = $this->reverse(
            $invoice,
            null,
            Carbon::today(),
            (string) __('commission.note.reassigned'),
            exceptUserId: $user?->id,
        );

        $commission = $this->accrue($invoice->refresh());

        return $commission === null ? $written : [...$written, $commission];
    }

    /**
     * Rueckrechnung am Ursprungsbeleg.
     *
     * @param  Money|null  $limit  Bemessungsgrundlage, die zurueckgenommen wird
     *                             (`null` = vollstaendig — Storno).
     * @param  int|null  $exceptUserId  Person, deren Zeile stehen bleibt
     *                                  (Umzuordnung auf dieselbe Person).
     * @return list<InvoiceCommission>
     */
    public function reverse(Invoice $invoice, ?Money $limit, Carbon $on, string $note, ?int $exceptUserId = null): array {
        return DB::transaction(function () use ($invoice, $limit, $on, $note, $exceptUserId): array {
            /** @var \Illuminate\Database\Eloquent\Collection<int, InvoiceCommission> $originals */
            $originals = InvoiceCommission::query()
                ->where('invoice_id', $invoice->id)
                ->whereNull('reversal_of_id')
                ->where('status', '!=', CommissionStatus::Reversed->value)
                ->when($exceptUserId !== null, fn (Builder $q): Builder => $q->where('user_id', '!=', $exceptUserId))
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

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
                    'commission_rule_id' => $original->commission_rule_id,
                    'assignment_source' => $original->assignment_source,
                    'lead_id' => $original->lead_id,
                    'currency' => $original->currency,
                    'base_amount' => $take->negated(),
                    'rate_percent' => $rate,
                    'commission_amount' => $rate->amountOf($take)->negated(),
                    'earned_on' => $on,
                    'status' => $neutralize ? CommissionStatus::Reversed : CommissionStatus::Pending,
                    'reversal_of_id' => $original->id,
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
