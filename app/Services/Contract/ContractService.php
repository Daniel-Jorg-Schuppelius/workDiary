<?php
/*
 * Created on   : Mon Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContractService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Contract;

use App\Enums\Contract\{ContractObligationKind, ContractStatus, ContractTermKind};
use App\Enums\Notification\NotificationEvent;
use App\Enums\Numbering\NumberScope;
use App\Models\AssetFinance\AssetFinanceContract;
use App\Models\Contract\{Contract, ContractObligation};
use App\Models\{Organization, User};
use App\Services\Concerns\AssertsStatusTransition;
use App\Services\Notification\NotificationDispatcher;
use App\Services\Numbering\NumberSequenceService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Lebenszyklus des allgemeinen Vertrags (Welle D, CLM): Anlage mit
 * Nummernkreis, Status-Statemachine, Berechnung des nächstmöglichen
 * Kündigungstermins (befristet/unbefristet, Mindestlaufzeit, automatische
 * Verlängerung), Obligationen-/Vertragskalender und dessen Fristen-/
 * Eskalationsscan sowie die additive Verknüpfung zum Leasing-/Finanzierungs-
 * modell (Feature 074). Keine externe Index-API — Indexierung ist deskriptiv.
 */
class ContractService {
    use AssertsStatusTransition;

    public function __construct(
        private readonly NumberSequenceService $numbers,
        private readonly NotificationDispatcher $notifier,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function create(Organization $organization, User $creator, array $attributes): Contract {
        return DB::transaction(fn (): Contract => Contract::query()->create(array_merge($attributes, [
            'organization_id' => $organization->id,
            'number' => $this->numbers->next($organization, NumberScope::Contract),
            'status' => ContractStatus::Draft->value,
            'created_by' => $creator->id,
        ])));
    }

    public function activate(Contract $contract, User $actor): Contract {
        $this->assertTransition($contract, ContractStatus::Active);

        return DB::transaction(function () use ($contract, $actor): Contract {
            $contract->forceFill(['status' => ContractStatus::Active->value])->save();
            $this->generateNoticeObligation($contract);
            $contract->audit('contract.activated', ['by' => $actor->id]);

            return $contract;
        });
    }

    public function terminate(Contract $contract, User $actor, ?string $reason = null): Contract {
        $this->assertTransition($contract, ContractStatus::Terminated);

        $contract->forceFill(['status' => ContractStatus::Terminated->value])->save();
        $contract->audit('contract.terminated', ['reason' => $reason, 'by' => $actor->id]);

        return $contract;
    }

    public function end(Contract $contract, User $actor): Contract {
        $this->assertTransition($contract, ContractStatus::Ended);

        $contract->forceFill([
            'status' => ContractStatus::Ended->value,
            'closed_at' => now(),
            'closed_by' => $actor->id,
        ])->save();
        $contract->audit('contract.ended', []);

        return $contract;
    }

    public function cancel(Contract $contract, User $actor): Contract {
        $this->assertTransition($contract, ContractStatus::Cancelled);

        $contract->forceFill(['status' => ContractStatus::Cancelled->value])->save();
        $contract->audit('contract.cancelled', ['by' => $actor->id]);

        return $contract;
    }

    /**
     * Nächstmöglicher ordentlicher Kündigungstermin.
     *
     * - Befristet ohne automatische Verlängerung: der Vertrag endet zum
     *   ends_on — der nächstmögliche (ordentliche) Beendigungstermin ist
     *   ends_on.
     * - Befristet mit automatischer Verlängerung (um renew_period_months):
     *   das früheste Perioden-Ende, dessen Kündigungsfrist (Ende −
     *   notice_period_days) noch nicht verstrichen ist. Ist die Frist der
     *   laufenden Periode bereits verpasst, rückt der Termin auf das nächste
     *   Verlängerungs-Ende.
     * - Unbefristet: frühestens Stichtag + Kündigungsfrist, nie vor Ablauf
     *   einer vereinbarten Mindestlaufzeit (starts_on + min_term_months).
     */
    public function nextTerminationDate(Contract $contract, ?CarbonInterface $from = null): Carbon {
        $from = $from !== null ? Carbon::instance($from)->startOfDay() : Carbon::today();
        $notice = max(0, (int) ($contract->notice_period_days ?? 0));

        if ($contract->term_kind === ContractTermKind::Fixed && $contract->ends_on !== null) {
            $periodEnd = $contract->ends_on->copy();

            if (! $contract->auto_renew) {
                return $periodEnd;
            }

            $step = max(1, (int) ($contract->renew_period_months ?? 12));
            $guard = 0;
            while ($periodEnd->copy()->subDays($notice)->lt($from) && $guard < 1200) {
                $periodEnd = $periodEnd->copy()->addMonthsNoOverflow($step);
                $guard++;
            }

            return $periodEnd;
        }

        // Unbefristet (oder befristet ohne Enddatum): frühestens ab Frist,
        // nie vor Ende der Mindestlaufzeit.
        $earliest = $from->copy()->addDays($notice);

        if ($contract->min_term_months !== null) {
            $minEnd = $contract->starts_on->copy()->addMonthsNoOverflow((int) $contract->min_term_months);
            if ($minEnd->gt($earliest)) {
                $earliest = $minEnd;
            }
        }

        return $earliest;
    }

    /**
     * Letzter Tag, an dem die Kündigung zum nächstmöglichen Termin noch
     * fristgerecht eingehen muss (Termin − Kündigungsfrist).
     */
    public function noticeDeadline(Contract $contract, ?CarbonInterface $from = null): Carbon {
        return $this->nextTerminationDate($contract, $from)
            ->copy()->subDays(max(0, (int) ($contract->notice_period_days ?? 0)));
    }

    /**
     * Erzeugt (idempotent) die Kündigungsfrist-Obligation zum aktuellen
     * Kündigungs-Stichtag, sofern eine Frist gesetzt ist und der Stichtag in
     * der Zukunft liegt. Speist den Vertragskalender + die Fristenmechanik.
     */
    public function generateNoticeObligation(Contract $contract): ?ContractObligation {
        $deadline = $this->noticeDeadline($contract);
        if ((int) ($contract->notice_period_days ?? 0) <= 0 || $deadline->isPast()) {
            return null;
        }

        $exists = $contract->obligations()
            ->where('kind', ContractObligationKind::NoticeDeadline->value)
            ->where('status', 'open')
            ->exists();
        if ($exists) {
            return null;
        }

        return $this->addObligation($contract, [
            'kind' => ContractObligationKind::NoticeDeadline->value,
            'title' => (string) __('Kündigungsfrist zum :date', ['date' => $this->nextTerminationDate($contract)->format('d.m.Y')]),
            'due_on' => $deadline->toDateString(),
            'warn_days_before' => 30,
            'responsible_user_id' => $contract->responsible_user_id,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    public function addObligation(Contract $contract, array $attributes): ContractObligation {
        $obligation = $contract->obligations()->create(array_merge($attributes, [
            'organization_id' => $contract->organization_id,
        ]));

        $contract->audit('contract.obligationAdded', [
            'obligation_id' => $obligation->id,
            'kind' => $obligation->kind->value,
        ]);

        return $obligation;
    }

    /**
     * Obligation erledigen. Wiederkehrende Termine erzeugen die nächste
     * Fälligkeit (due_on + recurrence_months) als neue offene Obligation.
     */
    public function completeObligation(ContractObligation $obligation, User $actor): ContractObligation {
        if ($obligation->status === 'done') {
            return $obligation;
        }

        return DB::transaction(function () use ($obligation, $actor): ContractObligation {
            $obligation->forceFill([
                'status' => 'done',
                'done_at' => now(),
                'done_by' => $actor->id,
            ])->save();

            if ($obligation->recurring && $obligation->recurrence_months !== null && $obligation->recurrence_months > 0) {
                $contract = $obligation->contract()->firstOrFail();
                $this->addObligation($contract, [
                    'kind' => $obligation->kind->value,
                    'title' => $obligation->title,
                    'due_on' => $obligation->due_on->copy()->addMonthsNoOverflow((int) $obligation->recurrence_months)->toDateString(),
                    'warn_days_before' => $obligation->warn_days_before,
                    'recurring' => true,
                    'recurrence_months' => $obligation->recurrence_months,
                    'responsible_user_id' => $obligation->responsible_user_id,
                    'note' => $obligation->note,
                ]);
            }

            $obligation->contract?->audit('contract.obligationCompleted', ['obligation_id' => $obligation->id]);

            return $obligation;
        });
    }

    /**
     * Fristen-Scan (Vertragskalender): Warnung ab Vorwarnzeit + Eskalation
     * über die Org-Regel; abgelaufene offene Obligationen laufender Verträge
     * werden als versäumt markiert. Payload trägt due_at → der Kalender-Kanal
     * (A11) publiziert den Termin automatisch.
     */
    public function scanObligations(Organization $organization): int {
        $sent = 0;

        $obligations = ContractObligation::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->open()
            ->with(['contract', 'responsible'])
            ->get();

        foreach ($obligations as $obligation) {
            $contract = $obligation->contract;
            if ($contract === null || ! $contract->status->isOpen()) {
                continue;
            }

            if ($obligation->due_on->endOfDay()->isPast()) {
                $obligation->forceFill(['status' => 'missed'])->save();
                $contract->audit('contract.obligationMissed', ['obligation_id' => $obligation->id]);
            }

            if (! $obligation->isDueForWarning()) {
                continue;
            }

            $payload = [
                'title' => (string) __('Vertragsfrist fällig: :kind (:number)', [
                    'kind' => $obligation->kind->label(),
                    'number' => $contract->number,
                ]),
                'title_key' => 'Vertragsfrist fällig: :kind (:number)',
                'title_params' => [
                    'kind' => ['key' => $obligation->kind->labelKey()],
                    'number' => $contract->number,
                ],
                'message' => (string) __(':title — fällig am :date.', [
                    'title' => $obligation->title,
                    'date' => $obligation->due_on->format('d.m.Y'),
                ]),
                'message_key' => ':title — fällig am :date.',
                'message_params' => [
                    'title' => $obligation->title,
                    'date' => $obligation->due_on->toDateString(),
                ],
                'url' => route('contracts.show', $contract),
                'due_at' => $obligation->due_on,
            ];

            $sent += $this->notifier->notify(NotificationEvent::ContractDeadlineDue, $obligation, $obligation->responsible, $payload, dedup: true);
            $sent += $this->notifier->escalateIfDue(NotificationEvent::ContractDeadlineDue, $obligation, $payload);
        }

        return $sent;
    }

    /**
     * Additive Verknüpfung eines Leasing-/Finanzierungsvertrags mit einem
     * allgemeinen Vertrag (Welle D). Org-Konsistenz wird erzwungen; der
     * Spezialfall bleibt eigenständig.
     */
    public function linkAssetFinanceContract(AssetFinanceContract $assetFinance, Contract $contract): AssetFinanceContract {
        if ((int) $assetFinance->organization_id !== (int) $contract->organization_id) {
            throw new \RuntimeException((string) __('Der Vertrag gehört zu einer anderen Organisation.'));
        }

        $assetFinance->forceFill(['contract_id' => $contract->id])->save();
        $assetFinance->audit('assetFinance.contractLinked', ['contract_id' => $contract->id]);

        return $assetFinance;
    }

    /** Gemeinsamer Guard (Vollaudit 2026-07, M44) — Semantik unverändert. */
    private function assertTransition(Contract $contract, ContractStatus $target): void {
        $this->assertStatusTransition($contract->status, $target);
    }
}
