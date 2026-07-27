<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvestmentService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Investments;

use App\Models\Investments\{InvestmentBudgetRequest, InvestmentCase, InvestmentDeviation};
use App\Models\User;
use App\Services\ServiceTicket\ApprovalService;
use Illuminate\Support\Facades\DB;

/**
 * Investitions-Lifecycle (Feature 069, MVP-202/203/205/206): versionierte
 * Budgetanträge mit Schwellenwert-Freigabekette (Vier-Augen ab
 * konfigurierbarer Grenze, Selbstfreigabe-Sperre), Sperre gegen stille
 * Budgeterhöhung (Nachtrag = neuer Antrag, alter Stand superseded),
 * Ist-Wert-Projektion aus verknüpften führenden Modulen und
 * Abweichungsmanagement.
 */
class InvestmentService {
    public function __construct(private readonly ApprovalService $approvals) {}

    /**
     * Budgetantrag einreichen: friert den Antragstand ein und baut die
     * Freigabekette — 1 Stufe unterhalb, 2 Stufen (Vier-Augen) ab dem
     * org-konfigurierbaren Schwellenwert (P1: Grenze ist Konfiguration).
     *
     * @param array<string, mixed> $attributes
     */
    public function submitBudget(InvestmentCase $case, array $attributes, User $actor): InvestmentBudgetRequest {
        if (! in_array($case->status, InvestmentCase::PLANNING_STATUSES, true)) {
            throw new \RuntimeException((string) __('Budgetanträge sind nur in der Planungsphase möglich.'));
        }
        if ($case->budgetRequests()->whereIn('status', ['draft', 'in_approval'])->exists()) {
            throw new \RuntimeException((string) __('Es ist bereits ein Budgetantrag offen.'));
        }

        return DB::transaction(function () use ($case, $attributes, $actor): InvestmentBudgetRequest {
            $amount = (string) $attributes['amount'];
            $request = InvestmentBudgetRequest::query()->create([
                'organization_id' => $case->organization_id,
                'investment_case_id' => $case->id,
                'version' => (int) $case->budgetRequests()->max('version') + 1,
                'amount' => $amount,
                'cost_kind' => (string) ($attributes['cost_kind'] ?? 'purchase'),
                'financing' => (string) ($attributes['financing'] ?? 'cash'),
                'payment_plan' => $attributes['payment_plan'] ?? null,
                'note' => $attributes['note'] ?? null,
                'status' => 'in_approval',
                'requested_by' => $actor->id,
            ]);

            $chain = [['rule' => ['kind' => 'commercial']]];
            if ((float) $amount >= $this->approvalThreshold($case)) {
                $chain[] = ['rule' => ['kind' => 'management']];
            }
            $this->approvals->createChain($request, $chain);

            $case->update(['status' => 'in_approval']);
            $case->audit('investment.budget_submitted', ['version' => $request->version, 'amount' => $amount]);

            return $request;
        });
    }

    /** Freigabestufe erteilen — Selbstfreigabe (Antragsteller) ist gesperrt. */
    public function approveBudget(InvestmentBudgetRequest $request, User $actor, ?string $reason = null): string {
        if ($request->status !== 'in_approval') {
            throw new \RuntimeException((string) __('Der Antrag ist nicht in Freigabe.'));
        }
        $pending = $request->approvals()
            ->where(fn($q) => $q->whereNull('decision')->orWhere('decision', 'question'))
            ->orderBy('step')
            ->first();
        if ($pending === null) {
            throw new \RuntimeException((string) __('Keine offene Freigabestufe.'));
        }

        $result = $this->approvals->decide($pending, $actor, 'approved', $reason, (int) $request->requested_by);
        if ($result === 'approved_all') {
            $case = $request->investmentCase()->firstOrFail();
            $request->update([
                'status' => 'approved',
                'decided_at' => now(),
                // Genehmigter Stand als unveränderlicher Snapshot (MVP-203).
                'snapshot' => [
                    'amount' => $request->amount,
                    'cost_kind' => $request->cost_kind,
                    'financing' => $request->financing,
                    'payment_plan' => $request->payment_plan,
                    'cost_center' => $case->costCenterDisplay(),
                    'approved_at' => now()->toIso8601String(),
                ],
            ]);
            $case->update(['status' => 'approved']);
            $case->audit('investment.budget_approved', ['version' => $request->version, 'amount' => $request->amount]);

            // Vollaudit 2026-07 (M31): Entscheidung an den Antragsteller.
            $this->notifyDecision($case, $request, 'investment_approved_title');
        }

        return $result;
    }

    public function rejectBudget(InvestmentBudgetRequest $request, User $actor, string $reason): void {
        if ($request->status !== 'in_approval') {
            throw new \RuntimeException((string) __('Der Antrag ist nicht in Freigabe.'));
        }
        $pending = $request->approvals()
            ->where(fn($q) => $q->whereNull('decision')->orWhere('decision', 'question'))
            ->orderBy('step')
            ->first();
        if ($pending === null) {
            throw new \RuntimeException((string) __('Keine offene Freigabestufe.'));
        }

        $this->approvals->decide($pending, $actor, 'rejected', $reason, (int) $request->requested_by);
        $request->update(['status' => 'rejected', 'decided_at' => now()]);
        $case = $request->investmentCase()->firstOrFail();
        $case->update(['status' => 'rejected']);
        $case->audit('investment.budget_rejected', ['version' => $request->version, 'reason' => $reason]);

        // Vollaudit 2026-07 (M31): Ablehnung inkl. Begründung an den Antragsteller.
        $this->notifyDecision($case, $request, 'investment_rejected_title', $reason);
    }

    /**
     * Vollaudit 2026-07 (M31): Budget-Entscheidung an den Antragsteller
     * melden (Feature 069, MVP-209 — Benachrichtigungsschiene).
     */
    private function notifyDecision(InvestmentCase $case, InvestmentBudgetRequest $request, string $titleKey, ?string $reason = null): void {
        $requester = $request->requested_by !== null
            ? User::query()->withoutGlobalScopes()->find((int) $request->requested_by)
            : null;

        app(\App\Services\Notification\NotificationDispatcher::class)->notify(
            \App\Enums\Notification\NotificationEvent::InvestmentDecided,
            $case,
            $requester,
            [
                'title' => (string) __('notification.message.' . $titleKey, ['title' => (string) $case->title]),
                'title_key' => 'notification.message.' . $titleKey,
                'title_params' => ['title' => (string) $case->title],
                'message' => $reason,
                'url' => route('investments.show', $case),
            ],
        );
    }

    /**
     * Nachtrag (MVP-206): Budgeterhöhung NUR über eine genehmigte
     * Budget-Abweichung — erzeugt einen neuen Antrag in Freigabe und
     * markiert den alten genehmigten Stand als superseded (nie löschen).
     *
     * @param array<string, mixed> $attributes
     */
    public function supplementBudget(InvestmentCase $case, InvestmentDeviation $deviation, array $attributes, User $actor): InvestmentBudgetRequest {
        if ($deviation->investment_case_id !== $case->id || $deviation->kind !== 'budget' || $deviation->status !== 'approved') {
            throw new \RuntimeException((string) __('Ein Nachtrag braucht eine genehmigte Budget-Abweichung.'));
        }
        $approved = $case->approvedBudget();
        if ($approved === null) {
            throw new \RuntimeException((string) __('Ohne genehmigtes Budget gibt es keinen Nachtrag.'));
        }

        return DB::transaction(function () use ($case, $approved, $attributes, $actor): InvestmentBudgetRequest {
            $request = InvestmentBudgetRequest::query()->create([
                'organization_id' => $case->organization_id,
                'investment_case_id' => $case->id,
                'version' => (int) $case->budgetRequests()->max('version') + 1,
                'amount' => (string) $attributes['amount'],
                'cost_kind' => $approved->cost_kind,
                'financing' => $approved->financing,
                'payment_plan' => $approved->payment_plan,
                'note' => $attributes['note'] ?? null,
                'status' => 'in_approval',
                'requested_by' => $actor->id,
            ]);

            $chain = [['rule' => ['kind' => 'commercial']]];
            if ((float) $attributes['amount'] >= $this->approvalThreshold($case)) {
                $chain[] = ['rule' => ['kind' => 'management']];
            }
            $this->approvals->createChain($request, $chain);

            $approved->update(['status' => 'superseded']);
            $case->update(['status' => 'in_approval']);
            $case->audit('investment.budget_supplement', ['from' => $approved->version, 'to' => $request->version]);

            return $request;
        });
    }

    public function decideDeviation(InvestmentDeviation $deviation, string $decision, ?string $note, User $actor): void {
        if (! in_array($decision, ['approved', 'rejected'], true)) {
            throw new \RuntimeException((string) __('Ungültige Entscheidung.'));
        }
        if ($deviation->status !== 'open') {
            throw new \RuntimeException((string) __('Die Abweichung ist bereits entschieden.'));
        }
        if ((int) $deviation->created_by === (int) $actor->id) {
            throw new \RuntimeException((string) __('Selbstfreigabe ist nicht zulässig.'));
        }

        $deviation->update([
            'status' => $decision,
            'decided_by' => $actor->id,
            'decided_at' => now(),
            'decision_note' => $note,
        ]);
        $case = $deviation->investmentCase()->firstOrFail();
        if ($decision === 'approved' && $deviation->kind === 'cancellation') {
            $case->update(['status' => 'cancelled']);
        }
        $case->audit('investment.deviation_decided', ['kind' => $deviation->kind, 'decision' => $decision]);
    }

    /**
     * Ist-Wert-Projektion (MVP-205): Summen aus manuellen Ist-Werten und
     * verknüpften führenden Objekten — genehmigtes Budget, gebundene
     * Mittel (Bestellungen), Ist-Kosten (Eingangsrechnungen/Assets/manuell).
     *
     * @return array{approved: float, committed: float, actual: float, remaining: float|null}
     */
    public function projection(InvestmentCase $case): array {
        $approvedRequest = $case->approvedBudget();
        $approved = $approvedRequest !== null ? (float) $approvedRequest->amount : 0.0;

        $committed = 0.0;
        $actual = (float) $case->actuals()->sum('amount');

        foreach ($case->links()->get() as $link) {
            $target = $link->linkable;
            if ($target instanceof \App\Models\PurchaseOrder) {
                // Mittelbindung: bestellte Positionssumme (Netto) + Fracht.
                $committed += (float) $target->lines()->get()->sum(
                    fn($line): float => (float) $line->getAttribute('quantity') * (float) $line->getAttribute('unit_price')
                );
                $committed += $target->freight_cost?->toFloat() ?? 0.0;
            } elseif ($target instanceof \App\Models\IncomingEInvoice) {
                $actual += (float) data_get($target->summary, 'gross', 0);
            } elseif ($target instanceof \App\Models\Asset) {
                $actual += (float) ($target->getAttribute('acquisition_cost') ?? 0);
            }
        }

        return [
            'approved' => round($approved, 2),
            'committed' => round($committed, 2),
            'actual' => round($actual, 2),
            'remaining' => $approved > 0 ? round($approved - $actual, 2) : null,
        ];
    }

    /** Org-Override vor Default (P1): settings.investments.approval_threshold. */
    private function approvalThreshold(InvestmentCase $case): float {
        $org = $case->organization()->first();
        $override = $org !== null ? data_get((array) $org->settings, 'investments.approval_threshold') : null;

        return $override !== null
            ? (float) $override
            : (float) config('investments.approval_threshold', 10000);
    }
}
