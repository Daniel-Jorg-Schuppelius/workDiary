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

use App\Enums\Approval\ApprovalStepKind;
use App\Enums\Investments\{InvestmentBudgetRequestStatus, InvestmentCaseStatus, InvestmentDeviationStatus};
use App\Enums\Notification\NotificationEvent;
use App\Enums\User\UserRole;
use App\Models\Approval\Approval;
use App\Models\Investments\{InvestmentBudgetRequest, InvestmentCase, InvestmentDeviation};
use App\Models\Platform\User;
use App\Services\Approval\{ApprovalResponsibility, ApprovalService};
use App\Services\Notification\NotificationDispatcher;
use App\Support\DocumentNumber;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
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
    public function __construct(
        private readonly ApprovalService $approvals,
        private readonly ApprovalResponsibility $responsibility,
    ) {}

    /**
     * Budgetantrag einreichen: friert den Antragstand ein und baut die
     * Freigabekette — 1 Stufe unterhalb, 2 Stufen (Vier-Augen) ab dem
     * org-konfigurierbaren Schwellenwert (P1: Grenze ist Konfiguration).
     *
     * @param array<string, mixed> $attributes
     */
    public function submitBudget(InvestmentCase $case, array $attributes, User $actor): InvestmentBudgetRequest {
        if (! $case->status->isPlanning()) {
            throw new \RuntimeException((string) __('Budgetanträge sind nur in der Planungsphase möglich.'));
        }
        if ($case->budgetRequests()->whereIn('status', InvestmentBudgetRequestStatus::open())->exists()) {
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
                'status' => InvestmentBudgetRequestStatus::InApproval,
                'requested_by' => $actor->id,
            ]);

            $chain = [['rule' => ['kind' => ApprovalStepKind::Commercial->value]]];
            if ((float) $amount >= $this->approvalThreshold($case)) {
                $chain[] = ['rule' => ['kind' => ApprovalStepKind::Management->value]];
            }
            $this->approvals->createChain($request, $chain);

            $case->update(['status' => InvestmentCaseStatus::InApproval]);
            $case->audit('investment.budget_submitted', ['version' => $request->version, 'amount' => $amount]);
            DB::afterCommit(fn () => $this->notifyOpenStep($request));

            return $request;
        });
    }

    /** Freigabestufe erteilen — Selbstfreigabe (Antragsteller) ist gesperrt. */
    public function approveBudget(InvestmentBudgetRequest $request, User $actor, ?string $reason = null): string {
        return $this->serialized($request, function () use ($request, $actor, $reason): string {
            if (! $request->status->canTransitionTo(InvestmentBudgetRequestStatus::Approved)) {
                throw new \RuntimeException((string) __('Der Antrag ist nicht in Freigabe.'));
            }

            return $this->applyDecision($request, $this->pendingStep($request), $actor, 'approved', $reason, null);
        });
    }

    public function rejectBudget(InvestmentBudgetRequest $request, User $actor, string $reason): void {
        $this->serialized($request, function () use ($request, $actor, $reason): string {
            if (! $request->status->canTransitionTo(InvestmentBudgetRequestStatus::Rejected)) {
                throw new \RuntimeException((string) __('Der Antrag ist nicht in Freigabe.'));
            }

            return $this->applyDecision($request, $this->pendingStep($request), $actor, 'rejected', $reason, null);
        });
    }

    /**
     * Entscheidung einer bestimmten Stufe aus dem Genehmigungs-Eingang
     * (MVP-1522) — mit denselben Folgen wie Freigabe und Ablehnung an der Akte.
     *
     * @return 'approved_all'|'rejected'|'pending'
     */
    public function decide(Approval $approval, User $actor, string $decision, ?string $reason, ?int $delegateUserId): string {
        $request = $approval->approvable;
        if (! $request instanceof InvestmentBudgetRequest) {
            throw new \LogicException('Die Stufe gehört zu keinem Budgetantrag.');
        }

        return $this->serialized($request, function () use ($request, $approval, $actor, $decision, $reason, $delegateUserId): string {
            if ($request->status !== InvestmentBudgetRequestStatus::InApproval) {
                throw new \RuntimeException((string) __('Der Antrag ist nicht in Freigabe.'));
            }

            return $this->applyDecision($request, $approval->refresh(), $actor, $decision, $reason, $delegateUserId);
        });
    }

    /** Niedrigste offene Stufe der geltenden Runde (Rückfrage zählt als offen); null ohne offene Stufe. */
    public function openStep(InvestmentBudgetRequest $request): ?Approval {
        return $request->status === InvestmentBudgetRequestStatus::InApproval ? $this->openSteps($request)->first() : null;
    }

    /**
     * Darf der Nutzer die offene Stufe entscheiden (MVP-1541)? Dieselbe Regel
     * wie im Genehmigungs-Eingang: zuständig für die Stufe, weder Antragsteller
     * noch Entscheider einer früheren Stufe derselben Runde.
     */
    public function mayDecide(InvestmentBudgetRequest $request, Approval $step, User $user): bool {
        return $this->responsibility->isResponsible($step, $user)
            && ! in_array((int) $user->id, $this->blockedDeciders($request, $step), true);
    }

    /** Wer die offene Stufe entscheidet, für den Hinweis an der Akte. */
    public function waitingFor(Approval $step): string {
        $kind = $this->responsibility->kindOf($step);
        $role = $this->responsibility->mappedRole($step);
        $rule = (array) $step->approver_rule;
        $who = match (true) {
            $kind !== null && $role !== null => (string) __('investment.approval.waiting_kind', ['kind' => $kind->label(), 'role' => $role->label()]),
            (string) ($rule['type'] ?? '') === 'role' => (string) __('investment.approval.waiting_role', [
                'role' => UserRole::tryFrom((string) ($rule['value'] ?? ''))?->label() ?? (string) ($rule['value'] ?? ''),
            ]),
            (string) ($rule['type'] ?? '') === 'user' => (string) (User::query()->whereKey((int) ($rule['value'] ?? 0))->value('name') ?? '—'),
            default => '—',
        };

        return (string) __('investment.approval.waiting', ['step' => $step->step, 'who' => $who]);
    }

    private function pendingStep(InvestmentBudgetRequest $request): Approval {
        $pending = $this->openSteps($request)->first();
        if ($pending === null) {
            throw new \RuntimeException((string) __('Keine offene Freigabestufe.'));
        }

        return $pending;
    }

    /** @return Builder<Approval> */
    private function openSteps(InvestmentBudgetRequest $request): Builder {
        return Approval::query()
            ->where('approvable_type', $request->getMorphClass())
            ->where('approvable_id', $request->getKey())
            ->currentRound()
            ->where(fn ($q) => $q->whereNull('decision')->orWhere('decision', 'question'))
            ->orderBy('step')
            ->orderBy('id');
    }

    /** @return 'approved_all'|'rejected'|'pending' */
    private function applyDecision(InvestmentBudgetRequest $request, Approval $approval, User $actor, string $decision, ?string $reason, ?int $delegateUserId): string {
        // An der Akte wie im Eingang: nur wer für die Stufe zuständig ist (MVP-1541).
        if (! $this->responsibility->isResponsible($approval, $actor)) {
            throw new AuthorizationException((string) __('investment.approval.not_responsible'));
        }
        $result = $this->approvals->decide($approval, $actor, $decision, $reason, (int) $request->requested_by, $delegateUserId);
        $case = $request->investmentCase()->firstOrFail();

        if ($result === 'approved_all') {
            $request->update([
                'status' => InvestmentBudgetRequestStatus::Approved,
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
            $case->update(['status' => InvestmentCaseStatus::Approved]);
            $case->audit('investment.budget_approved', ['version' => $request->version, 'amount' => $request->amount]);

            // Vollaudit 2026-07 (M31): Entscheidung an den Antragsteller.
            $this->notifyDecision($case, $request, 'investment_approved_title');

            return $result;
        }

        if ($result === 'rejected') {
            $request->update(['status' => InvestmentBudgetRequestStatus::Rejected, 'decided_at' => now()]);
            $case->update(['status' => InvestmentCaseStatus::Rejected]);
            $case->audit('investment.budget_rejected', ['version' => $request->version, 'reason' => $reason]);

            // Vollaudit 2026-07 (M31): Ablehnung inkl. Begründung an den Antragsteller.
            $this->notifyDecision($case, $request, 'investment_rejected_title', $reason);

            return $result;
        }

        $case->audit(match ($decision) {
            'approved' => 'investment.budget_step_approved',
            'question' => 'investment.budget_question',
            default => 'investment.budget_delegated',
        }, ['version' => $request->version, 'step' => $approval->step, 'round' => $approval->round]);
        // Nächste Stufe oder Delegat übernimmt; eine Rückfrage bleibt bei derselben Stufe.
        if ($decision !== 'question') {
            DB::afterCommit(fn () => $this->notifyOpenStep($request));
        }

        return $result;
    }

    /**
     * Entscheidungen je Antrag nacheinander: zwei Freigebende dürfen dieselbe
     * Stufe nicht gleichzeitig entscheiden.
     *
     * @template T
     *
     * @param  Closure(): T  $work
     * @return T
     */
    private function serialized(InvestmentBudgetRequest $request, Closure $work): mixed {
        return DB::transaction(function () use ($request, $work): mixed {
            $request->newQueryWithoutScopes()->whereKey($request->getKey())->lockForUpdate()->value('id');
            $request->refresh();

            return $work();
        });
    }

    /**
     * Antragsteller und Entscheider früherer Stufen derselben Runde — sie
     * dürfen die Stufe nicht mehr entscheiden.
     *
     * @return list<int>
     */
    private function blockedDeciders(InvestmentBudgetRequest $request, Approval $step): array {
        return array_values(Approval::query()
            ->where('approvable_type', $step->approvable_type)
            ->where('approvable_id', $step->approvable_id)
            ->where('round', $step->round)
            ->whereIn('decision', ['approved', 'rejected', 'delegated'])
            ->pluck('decided_by')
            ->push($request->requested_by)
            ->filter()
            ->map(static fn ($id): int => (int) $id)
            ->all());
    }

    /** Zuständige der nächsten offenen Stufe benachrichtigen (MVP-1522), ohne die gesperrten. */
    private function notifyOpenStep(InvestmentBudgetRequest $request): void {
        $step = $this->openSteps($request)->first();
        $case = $request->investmentCase()->first();
        if ($step === null || $case === null) {
            return;
        }
        $blocked = $this->blockedDeciders($request, $step);

        $params = [
            'amount' => DocumentNumber::money((string) $request->amount),
            'step' => $step->step,
            'kind' => (string) $this->responsibility->kindOf($step)?->label(),
        ];
        $dispatcher = app(NotificationDispatcher::class);
        foreach ($this->responsibility->responsibleUsers($step) as $recipient) {
            if (in_array((int) $recipient->id, $blocked, true)) {
                continue;
            }
            $dispatcher->notify(NotificationEvent::InvestmentApprovalRequested, $case, $recipient, [
                'title' => (string) __('investment.approval.requested_title', ['title' => (string) $case->title]),
                'title_key' => 'investment.approval.requested_title',
                'title_params' => ['title' => (string) $case->title],
                'message' => (string) __('investment.approval.requested_message', $params),
                'message_key' => 'investment.approval.requested_message',
                'message_params' => $params,
                'url' => route('investments.show', $case),
            ]);
        }
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
        if ($deviation->investment_case_id !== $case->id || $deviation->kind !== 'budget' || $deviation->status !== InvestmentDeviationStatus::Approved) {
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
                'status' => InvestmentBudgetRequestStatus::InApproval,
                'requested_by' => $actor->id,
            ]);

            $chain = [['rule' => ['kind' => ApprovalStepKind::Commercial->value]]];
            if ((float) $attributes['amount'] >= $this->approvalThreshold($case)) {
                $chain[] = ['rule' => ['kind' => ApprovalStepKind::Management->value]];
            }
            $this->approvals->createChain($request, $chain);

            $approved->update(['status' => InvestmentBudgetRequestStatus::Superseded]);
            $case->update(['status' => InvestmentCaseStatus::InApproval]);
            $case->audit('investment.budget_supplement', ['from' => $approved->version, 'to' => $request->version]);
            DB::afterCommit(fn () => $this->notifyOpenStep($request));

            return $request;
        });
    }

    public function decideDeviation(InvestmentDeviation $deviation, string $decision, ?string $note, User $actor): void {
        $target = InvestmentDeviationStatus::tryFrom($decision);
        if (! in_array($target, [InvestmentDeviationStatus::Approved, InvestmentDeviationStatus::Rejected], true)) {
            throw new \RuntimeException((string) __('Ungültige Entscheidung.'));
        }
        if (! $deviation->status->canTransitionTo($target)) {
            throw new \RuntimeException((string) __('Die Abweichung ist bereits entschieden.'));
        }
        if ((int) $deviation->created_by === (int) $actor->id) {
            throw new \RuntimeException((string) __('Selbstfreigabe ist nicht zulässig.'));
        }
        $case = $deviation->investmentCase()->firstOrFail();
        $cancels = $target === InvestmentDeviationStatus::Approved && $deviation->kind === 'cancellation';
        if ($cancels && $case->status === InvestmentCaseStatus::Rejected) {
            throw new \RuntimeException((string) __('Eine abgelehnte Akte bleibt abgelehnt.'));
        }

        $deviation->update([
            'status' => $target,
            'decided_by' => $actor->id,
            'decided_at' => now(),
            'decision_note' => $note,
        ]);
        if ($cancels) {
            $case->update(['status' => InvestmentCaseStatus::Cancelled]);
        }
        $case->audit('investment.deviation_decided', ['kind' => $deviation->kind, 'decision' => $decision]);
    }

    /** Zurückstellen aus der Planung; die Phase bleibt für {@see self::resume()} gemerkt. */
    public function defer(InvestmentCase $case): void {
        if (! $case->status->isPlanning()) {
            throw new \RuntimeException((string) __('Zurückstellen ist nur in der Planungsphase möglich.'));
        }

        $case->update(['status' => InvestmentCaseStatus::Deferred, 'deferred_from_status' => $case->status]);
    }

    /** Wieder aufnehmen: zurück in die gemerkte Phase, Altbestand ohne Phase in die Idee. */
    public function resume(InvestmentCase $case): void {
        if ($case->status !== InvestmentCaseStatus::Deferred) {
            throw new \RuntimeException((string) __('Nur eine zurückgestellte Akte lässt sich wieder aufnehmen.'));
        }

        $case->update(['status' => $case->resumeTarget(), 'deferred_from_status' => null]);
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
            if ($target instanceof \App\Models\Procurement\PurchaseOrder) {
                // Mittelbindung: bestellte Positionssumme (Netto) + Fracht.
                // unit_price/ordered_qty sind Wertobjekte (Money/Quantity); die Menge heißt ordered_qty.
                $committed += (float) $target->lines()->get()->sum(
                    fn(\App\Models\Procurement\PurchaseOrderLine $line): float => $line->unit_price
                        ?->times($line->ordered_qty?->getNumericValue() ?? '0')
                        ->toFloat() ?? 0.0
                );
                $committed += $target->freight_cost?->toFloat() ?? 0.0;
            } elseif ($target instanceof \App\Models\Invoicing\IncomingEInvoice) {
                $actual += (float) data_get($target->summary, 'gross', 0);
            } elseif ($target instanceof \App\Models\Asset\Asset) {
                $actual += (float) ($target->getAttribute('acquisition_cost') ?? 0);
            } elseif ($target instanceof \App\Models\Accounting\FixedAsset) {
                // Aktivierte Anlage (MVP-909): Anschaffungskosten als Ist.
                $actual += $target->acquisition_cost?->toFloat() ?? 0.0;
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
