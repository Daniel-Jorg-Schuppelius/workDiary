<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetFinanceService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\AssetFinance;

use App\Enums\AssetFinance\{AssetFinanceEndKind, AssetFinanceStatus};
use App\Enums\Notification\NotificationEvent;
use App\Enums\Numbering\NumberScope;
use App\Models\AssetFinance\{AssetFinanceContract, AssetFinanceContractAsset, AssetFinanceDeadline, AssetFinanceEndProcess, AssetFinanceOption, AssetFinanceRateSchedule, AssetFinanceUsageLimit};
use App\Models\{IncomingEInvoice, Organization, User};
use App\Services\Concerns\AssertsStatusTransition;
use App\Services\Notification\NotificationDispatcher;
use App\Services\Numbering\NumberSequenceService;
use Illuminate\Support\Facades\DB;

/**
 * Lebenszyklus der Leasing-/Finanzierungsakte (Feature 074): Anlage mit
 * Konditionen-Snapshot (P2/D11), Ratenplan, Fristenkalender, Nutzungslimits
 * mit referenzierten Ist-Werten, Rückgabe-/Ende-Prozess und Soll-/Ist-
 * Projektion. Keine Bilanzierung, keine Buchung — Beleg- und Zahlungshoheit
 * bleiben beim Rechnungswesen (W11).
 */
class AssetFinanceService {
    use AssertsStatusTransition;

    public function __construct(
        private readonly NumberSequenceService $numbers,
        private readonly NotificationDispatcher $notifier,
    ) {}

    /**
     * @param array<string, mixed> $attributes
     * @param list<int> $assetIds
     */
    public function create(Organization $organization, User $creator, array $attributes, array $assetIds = []): AssetFinanceContract {
        return DB::transaction(function () use ($organization, $creator, $attributes, $assetIds): AssetFinanceContract {
            $contract = AssetFinanceContract::query()->create(array_merge($attributes, [
                'organization_id' => $organization->id,
                'number' => $this->numbers->next($organization, NumberScope::AssetFinance),
                'status' => AssetFinanceStatus::Draft->value,
                'created_by' => $creator->id,
            ]));

            foreach (array_unique($assetIds) as $assetId) {
                AssetFinanceContractAsset::query()->create([
                    'organization_id' => $organization->id,
                    'asset_finance_contract_id' => $contract->id,
                    'asset_id' => $assetId,
                ]);
            }

            return $contract;
        });
    }

    /**
     * Aktivierung friert die Konditionen als Snapshot ein (P2) und erzeugt
     * Ratenplan-Zeilen für die Laufzeit.
     */
    public function activate(AssetFinanceContract $contract, User $actor): AssetFinanceContract {
        $this->assertTransition($contract, AssetFinanceStatus::Active);

        return DB::transaction(function () use ($contract, $actor): AssetFinanceContract {
            $contract->forceFill([
                'status' => AssetFinanceStatus::Active->value,
                'terms_snapshot' => $this->buildSnapshot($contract),
            ])->save();

            $this->generateRateSchedule($contract);

            $contract->audit('assetFinance.activated', ['by' => $actor->id]);

            return $contract;
        });
    }

    /**
     * Soll-Ratenplan aus Rate + Zahlungsrhythmus über die Laufzeit —
     * idempotent (vorhandene Zeilen bleiben unangetastet).
     */
    public function generateRateSchedule(AssetFinanceContract $contract): int {
        if ($contract->rate_amount === null || $contract->ends_on === null) {
            return 0;
        }

        if ($contract->rateSchedules()->exists()) {
            return 0;
        }

        $stepMonths = match ((string) $contract->payment_rhythm) {
            'quarterly' => 3,
            'yearly' => 12,
            default => 1,
        };

        $created = 0;
        $due = $contract->starts_on->copy();

        while ($due <= $contract->ends_on) {
            AssetFinanceRateSchedule::query()->create([
                'organization_id' => $contract->organization_id,
                'asset_finance_contract_id' => $contract->id,
                'due_on' => $due->toDateString(),
                'amount' => $contract->rate_amount,
                'status' => 'planned',
            ]);
            $created++;
            $due = $due->copy()->addMonthsNoOverflow($stepMonths);
        }

        return $created;
    }

    /**
     * Ist-Referenz (MVP-274): Ratenzeile mit Eingangsrechnung verknüpfen —
     * keine Zahlung, keine Buchung, nur Nachweis (D11).
     */
    public function linkIncomingInvoice(AssetFinanceRateSchedule $schedule, IncomingEInvoice $invoice): AssetFinanceRateSchedule {
        if ((int) $schedule->organization_id !== (int) $invoice->organization_id) {
            throw new \RuntimeException((string) __('Eingangsrechnung gehört zu einer anderen Organisation.'));
        }

        $schedule->forceFill([
            'incoming_einvoice_id' => $invoice->id,
            'status' => 'paid',
            'paid_at' => now(),
        ])->save();

        $schedule->contract?->audit('assetFinance.rateLinked', [
            'schedule_id' => $schedule->id,
            'incoming_einvoice_id' => $invoice->id,
        ]);

        return $schedule;
    }

    /**
     * Ist-Wert eines Nutzungslimits erfassen (MVP-275) — manuell oder aus
     * dem letzten Zählerstand der Vertrags-Assets übernommen.
     */
    public function recordUsage(AssetFinanceUsageLimit $limit, User $actor, ?float $value = null): AssetFinanceUsageLimit {
        if ($value === null) {
            $assetIds = $limit->contract?->contractAssets()->pluck('asset_id') ?? collect();
            $latest = \App\Models\MeterReading::query()
                ->whereIn('asset_id', $assetIds)
                ->orderByDesc('read_at')
                ->first();

            if ($latest === null) {
                throw new \RuntimeException((string) __('Kein Zählerstand vorhanden — Ist-Wert manuell erfassen.'));
            }

            $value = (float) $latest->value;
        }

        $limit->forceFill([
            'actual_value' => $value,
            'actual_recorded_at' => now(),
        ])->save();

        $limit->contract?->audit('assetFinance.usageRecorded', [
            'limit_id' => $limit->id,
            'value' => (string) $value,
            'overrun' => (string) $limit->overrun(),
        ]);

        return $limit;
    }

    /**
     * Option ausüben (MVP-276): auditiert; Verlängerungsoptionen verschieben
     * das Vertragsende über den Ende-Prozess.
     */
    public function exerciseOption(AssetFinanceOption $option, User $actor): AssetFinanceOption {
        if (! $option->isExercisable()) {
            throw new \RuntimeException((string) __('Die Option ist nicht (mehr) ausübbar.'));
        }

        $option->forceFill([
            'exercised_at' => now(),
            'exercised_by' => $actor->id,
        ])->save();

        $option->contract?->audit('assetFinance.optionExercised', [
            'option_id' => $option->id,
            'kind' => $option->kind,
        ]);

        return $option;
    }

    /**
     * Ende-Prozess abschließen (MVP-276): setzt den Folgestatus der Akte
     * (Rückgabe/Kauf/Verlängerung) und dokumentiert die Entscheidung.
     */
    public function completeEndProcess(AssetFinanceEndProcess $endProcess, User $actor): AssetFinanceEndProcess {
        if ($endProcess->status === 'completed') {
            return $endProcess;
        }

        return DB::transaction(function () use ($endProcess, $actor): AssetFinanceEndProcess {
            $contract = $endProcess->contract()->firstOrFail();
            $target = $endProcess->kind->resultingStatus();

            $this->assertTransition($contract, $target);

            $endProcess->forceFill([
                'status' => 'completed',
                'decided_by' => $actor->id,
                'decided_at' => now(),
            ])->save();

            $updates = ['status' => $target->value];
            if ($endProcess->kind === AssetFinanceEndKind::Extension && $endProcess->new_ends_on !== null) {
                $updates['ends_on'] = $endProcess->new_ends_on->toDateString();
            }
            $contract->forceFill($updates)->save();

            $contract->audit('assetFinance.ended', [
                'kind' => $endProcess->kind->value,
                'status' => $target->value,
                'follow_up_amount' => $endProcess->follow_up_amount !== null ? (string) $endProcess->follow_up_amount : null,
            ]);

            return $endProcess;
        });
    }

    public function terminate(AssetFinanceContract $contract, User $actor, ?string $reason = null): AssetFinanceContract {
        $this->assertTransition($contract, AssetFinanceStatus::Terminated);

        $contract->forceFill(['status' => AssetFinanceStatus::Terminated->value])->save();
        $contract->audit('assetFinance.terminated', ['reason' => $reason, 'by' => $actor->id]);

        return $contract;
    }

    public function close(AssetFinanceContract $contract, User $actor): AssetFinanceContract {
        $this->assertTransition($contract, AssetFinanceStatus::Closed);

        $contract->forceFill([
            'status' => AssetFinanceStatus::Closed->value,
            'closed_at' => now(),
            'closed_by' => $actor->id,
        ])->save();
        $contract->audit('assetFinance.closed', []);

        return $contract;
    }

    /**
     * Soll-/Ist-Projektion (MVP-277): geplante Raten, referenzierte
     * Eingangsrechnungen und Limit-Überschreitungen — reine Sicht.
     *
     * @return array{planned: float, referenced: float, open: float, overruns: array<int, array<string, mixed>>}
     */
    public function projection(AssetFinanceContract $contract): array {
        $schedules = $contract->rateSchedules()->get();

        $planned = round((float) $schedules->sum(fn (AssetFinanceRateSchedule $s) => (float) $s->amount), 2);
        $referenced = round((float) $schedules->where('status', 'paid')->sum(fn (AssetFinanceRateSchedule $s) => (float) $s->amount), 2);

        $overruns = $contract->usageLimits()->get()
            ->filter(fn (AssetFinanceUsageLimit $limit): bool => $limit->overrun() > 0)
            ->map(fn (AssetFinanceUsageLimit $limit): array => [
                'kind' => $limit->kind->value,
                'limit' => (float) $limit->limit_value,
                'actual' => (float) $limit->actual_value,
                'overrun' => $limit->overrun(),
                'estimated_fee' => $limit->overrun_fee_per_unit !== null
                    ? round($limit->overrun() * (float) $limit->overrun_fee_per_unit, 2)
                    : null,
            ])
            ->values()
            ->all();

        return [
            'planned' => $planned,
            'referenced' => $referenced,
            'open' => round($planned - $referenced, 2),
            'overruns' => $overruns,
        ];
    }

    /**
     * Fristen-Scan (MVP-273/278): Warnung ab Vorwarnzeit, Eskalation über
     * die Org-Regel; abgelaufene offene Fristen werden als versäumt markiert.
     */
    public function scanDeadlines(Organization $organization): int {
        $sent = 0;

        $deadlines = AssetFinanceDeadline::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->open()
            ->with(['contract', 'responsible'])
            ->get();

        foreach ($deadlines as $deadline) {
            if ($deadline->due_on->endOfDay()->isPast()) {
                $deadline->forceFill(['status' => 'missed'])->save();
                $deadline->contract?->audit('assetFinance.deadlineMissed', ['deadline_id' => $deadline->id]);
            }

            if (! $deadline->isDueForWarning()) {
                continue;
            }

            $contract = $deadline->contract;
            $payload = [
                'title' => (string) __('Leasingfrist fällig: :kind (:number)', [
                    'kind' => $deadline->kind->label(),
                    'number' => $contract->number ?? '—',
                ]),
                'title_key' => 'Leasingfrist fällig: :kind (:number)',
                'title_params' => [
                    'kind' => ['key' => $deadline->kind->labelKey()],
                    'number' => $contract->number ?? '—',
                ],
                'message' => (string) __('Fällig am :date.', ['date' => $deadline->due_on->format('d.m.Y')]),
                'message_key' => 'Fällig am :date.',
                'message_params' => ['date' => $deadline->due_on->toDateString()],
                'url' => $contract !== null ? route('asset-finance.show', $contract) : null,
            ];

            $sent += $this->notifier->notify(NotificationEvent::AssetFinanceDeadline, $deadline, $deadline->responsible, $payload, dedup: true);
            $sent += $this->notifier->escalateIfDue(NotificationEvent::AssetFinanceDeadline, $deadline, $payload);
        }

        return $sent;
    }

    /** @return array<string, mixed> */
    private function buildSnapshot(AssetFinanceContract $contract): array {
        return [
            'frozen_at' => now()->toIso8601String(),
            'kind' => $contract->kind->value,
            'rate_amount' => $contract->rate_amount !== null ? (string) $contract->rate_amount : null,
            'payment_rhythm' => (string) $contract->payment_rhythm,
            'special_payment' => $contract->special_payment !== null ? (string) $contract->special_payment : null,
            'residual_value' => $contract->residual_value !== null ? (string) $contract->residual_value : null,
            'purchase_option_amount' => $contract->purchase_option_amount !== null ? (string) $contract->purchase_option_amount : null,
            'terms' => $contract->terms()->get()->map(fn ($term): array => [
                'kind' => $term->kind->value,
                'label' => $term->label,
                'amount' => $term->amount !== null ? (string) $term->amount : null,
                'unit' => $term->unit,
            ])->values()->all(),
            'usage_limits' => $contract->usageLimits()->get()->map(fn (AssetFinanceUsageLimit $limit): array => [
                'kind' => $limit->kind->value,
                'limit' => (string) $limit->limit_value,
                'period' => $limit->period,
            ])->values()->all(),
        ];
    }

    /** Gemeinsamer Guard (Vollaudit 2026-07, M44) — Semantik unverändert. */
    private function assertTransition(AssetFinanceContract $contract, AssetFinanceStatus $target): void {
        $this->assertStatusTransition($contract->status, $target);
    }
}
