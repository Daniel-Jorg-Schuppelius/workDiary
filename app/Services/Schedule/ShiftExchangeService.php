<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShiftExchangeService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Schedule;

use App\Enums\Notification\NotificationEvent;
use App\Enums\Shift\{ScheduledShiftStatus, ShiftExchangeStatus};
use App\Models\{Organization, ScheduledShift, ShiftExchange, User};
use App\Services\Compliance\{ComplianceReport, ShiftComplianceService};
use App\Services\Notification\NotificationDispatcher;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Statusmaschine + Umsetzung des Schichttauschs (Feature 007).
 *
 * Übergänge:
 *   requested → accepted   (Ziel-Kollege akzeptiert; nur wenn target_user)
 *   requested → approved   (Leitung gibt direkt frei, offene Abgabe)
 *   accepted  → approved   (Leitung gibt frei)
 *   requested/accepted → rejected   (Leitung lehnt ab)
 *   requested/accepted → cancelled  (Antragsteller nimmt zurück)
 *
 * Bei der Freigabe wird über den {@see ShiftComplianceService} geprüft, ob die
 * neue Zuordnung Compliance-konform ist (KEINE parallele Logik). Hard-ERROR
 * blockt die Freigabe (override nur durch die Leitung möglich).
 */
class ShiftExchangeService {
    public function __construct(
        private readonly ShiftComplianceService $compliance,
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    /**
     * Mitarbeiter beantragt Abgabe/Tausch einer Schicht.
     */
    public function request(
        ScheduledShift $shift,
        User $requester,
        ?User $target = null,
        ?ScheduledShift $offeredShift = null,
        ?string $reason = null,
    ): ShiftExchange {
        if ((int) $shift->user_id !== (int) $requester->id) {
            throw new ShiftExchangeException(__('schedule.exchange.error_not_owner'));
        }
        if ($shift->status === ScheduledShiftStatus::Cancelled) {
            throw new ShiftExchangeException(__('schedule.exchange.error_cancelled_shift'));
        }

        $exchange = ShiftExchange::create([
            'organization_id' => $shift->organization_id,
            'scheduled_shift_id' => $shift->id,
            'requested_by_user_id' => $requester->id,
            'target_user_id' => $target?->id,
            'offered_shift_id' => $offeredShift?->id,
            'status' => ShiftExchangeStatus::Requested,
            'reason' => $reason,
        ]);

        $this->notifyRequested($exchange, $target);

        return $exchange;
    }

    /**
     * Der gewünschte Ziel-Kollege akzeptiert den Tausch.
     */
    public function accept(ShiftExchange $exchange, User $actor): ShiftExchange {
        if ($exchange->status !== ShiftExchangeStatus::Requested) {
            throw new ShiftExchangeException(__('schedule.exchange.error_not_requestable'));
        }
        if ($exchange->target_user_id !== null && (int) $exchange->target_user_id !== (int) $actor->id) {
            throw new ShiftExchangeException(__('schedule.exchange.error_not_target'));
        }

        // Offene Abgabe ohne festen Ziel-Kollegen: der Annehmende wird zum Ziel.
        $exchange->update([
            'status' => ShiftExchangeStatus::Accepted,
            'target_user_id' => $exchange->target_user_id ?? $actor->id,
        ]);

        return $exchange;
    }

    /**
     * Antragsteller nimmt seinen Antrag zurück.
     */
    public function cancel(ShiftExchange $exchange, User $actor): ShiftExchange {
        if (! $exchange->status->isOpen()) {
            throw new ShiftExchangeException(__('schedule.exchange.error_not_open'));
        }
        if ((int) $exchange->requested_by_user_id !== (int) $actor->id) {
            throw new ShiftExchangeException(__('schedule.exchange.error_not_owner'));
        }

        $exchange->update(['status' => ShiftExchangeStatus::Cancelled]);

        return $exchange;
    }

    /**
     * Teamleitung lehnt ab.
     */
    public function reject(ShiftExchange $exchange, User $decider, ?string $reason = null): ShiftExchange {
        if (! $exchange->status->isDecidable()) {
            throw new ShiftExchangeException(__('schedule.exchange.error_not_decidable'));
        }

        $exchange->update([
            'status' => ShiftExchangeStatus::Rejected,
            'decided_by_user_id' => $decider->id,
            'decided_at' => Carbon::now(),
            'reason' => $reason ?? $exchange->reason,
        ]);

        $this->notifyDecided($exchange);

        return $exchange;
    }

    /**
     * Compliance-Vorabprüfung für die geplante Zuordnung nach Freigabe.
     * Liefert je Schicht-ID den Report (für eine Vorschau in der UI).
     *
     * @return array<int, ComplianceReport>
     */
    public function previewCompliance(ShiftExchange $exchange): array {
        $exchange->loadMissing(['scheduledShift', 'offeredShift']);
        $organization = $this->organizationFor($exchange);
        $targetUserId = (int) ($exchange->target_user_id ?? 0);

        $reports = [];
        if ($exchange->scheduledShift !== null && $targetUserId > 0) {
            $reports[(int) $exchange->scheduledShift->id] = $this->compliance->check(
                $this->reassignProxy($exchange->scheduledShift, $targetUserId),
                $organization,
            );
        }
        if ($exchange->offeredShift !== null) {
            $reports[(int) $exchange->offeredShift->id] = $this->compliance->check(
                $this->reassignProxy($exchange->offeredShift, (int) $exchange->requested_by_user_id),
                $organization,
            );
        }

        return $reports;
    }

    /**
     * Teamleitung gibt frei: Compliance-Check, dann Umsetzung der Zuordnung.
     *
     * @throws ShiftExchangeException bei ungültigem Status oder Compliance-Blockade
     */
    public function approve(ShiftExchange $exchange, User $decider, bool $overrideCompliance = false): ShiftExchange {
        if (! $exchange->status->isDecidable()) {
            throw new ShiftExchangeException(__('schedule.exchange.error_not_decidable'));
        }
        $exchange->loadMissing(['scheduledShift', 'offeredShift']);

        $shift = $exchange->scheduledShift;
        if ($shift === null || $shift->status === ScheduledShiftStatus::Cancelled) {
            throw new ShiftExchangeException(__('schedule.exchange.error_cancelled_shift'));
        }

        $targetUserId = (int) ($exchange->target_user_id ?? 0);
        if ($targetUserId <= 0) {
            throw new ShiftExchangeException(__('schedule.exchange.error_no_target'));
        }

        // Compliance-Prüfung der neuen Zuordnung (KEINE parallele Logik).
        if (! $overrideCompliance) {
            foreach ($this->previewCompliance($exchange) as $report) {
                if ($report->hasErrors()) {
                    throw new ShiftExchangeException(__('schedule.exchange.error_compliance'));
                }
            }
        }

        DB::transaction(function () use ($exchange, $shift, $targetUserId, $decider): void {
            $shift->update(['user_id' => $targetUserId, 'updated_by' => $decider->id]);

            // Echter Tausch: Gegenschicht geht an den Antragsteller.
            if ($exchange->offeredShift !== null) {
                $exchange->offeredShift->update([
                    'user_id' => $exchange->requested_by_user_id,
                    'updated_by' => $decider->id,
                ]);
            }

            $exchange->update([
                'status' => ShiftExchangeStatus::Approved,
                'decided_by_user_id' => $decider->id,
                'decided_at' => Carbon::now(),
            ]);
        });

        $this->notifyDecided($exchange);

        return $exchange;
    }

    private function notifyRequested(ShiftExchange $exchange, ?User $target): void {
        $exchange->loadMissing('scheduledShift.shiftType');
        $payload = [
            'title' => (string) __('schedule.exchange.notification_request_title'),
            'title_key' => 'schedule.exchange.notification_request_title',
            'message' => (string) __('schedule.exchange.notification_request_message', [
                'date' => $exchange->scheduledShift?->date?->format('d.m.Y') ?? '–',
            ]),
            'message_key' => 'schedule.exchange.notification_request_message',
            'message_params' => ['date' => $exchange->scheduledShift?->date?->toDateString() ?? '–'],
            'url' => $this->safeRoute('schedule.exchanges.index'),
        ];

        // An Teamleitung (Default-Empfängerrolle) sowie optional an den
        // Ziel-Kollegen (als betroffene Person dieser Benachrichtigung).
        $this->dispatcher->notify(
            NotificationEvent::ShiftExchangeRequested,
            $exchange,
            $target,
            $payload,
            dedup: false,
        );
    }

    private function notifyDecided(ShiftExchange $exchange): void {
        $requester = $exchange->requester()->first();
        $exchange->loadMissing('scheduledShift');
        $payload = [
            'title' => (string) __('schedule.exchange.notification_decided_title'),
            'title_key' => 'schedule.exchange.notification_decided_title',
            'message' => (string) __('schedule.exchange.notification_decided_message', [
                'status' => $exchange->status->label(),
                'date' => $exchange->scheduledShift?->date?->format('d.m.Y') ?? '–',
            ]),
            'message_key' => 'schedule.exchange.notification_decided_message',
            'message_params' => [
                'status' => ['key' => 'enums.shift.exchange_status.' . $exchange->status->value, 'fallback' => $exchange->status->label()],
                'date' => $exchange->scheduledShift?->date?->toDateString() ?? '–',
            ],
            'url' => $this->safeRoute('schedule.exchanges.index'),
        ];

        $this->dispatcher->notify(
            NotificationEvent::ShiftExchangeDecided,
            $exchange,
            $requester,
            $payload,
            dedup: false,
        );
    }

    private function organizationFor(ShiftExchange $exchange): ?Organization {
        return $exchange->organization_id
            ? Organization::query()->find($exchange->organization_id)
            : null;
    }

    /**
     * Proxy mit echter id (für Selbst-Ausschluss in den Regeln) und neuem User.
     */
    private function reassignProxy(ScheduledShift $shift, int $newUserId): ScheduledShift {
        $proxy = new ScheduledShift;
        $proxy->forceFill([
            'id' => $shift->id,
            'organization_id' => $shift->organization_id,
            'duty_plan_id' => $shift->duty_plan_id,
            'user_id' => $newUserId,
            'shift_type_id' => $shift->shift_type_id,
            'date' => $shift->date->toDateString(),
            'start_time' => $shift->start_time,
            'end_time' => $shift->end_time,
            'status' => $shift->status->value,
        ]);
        $proxy->setRelation('shiftType', $shift->shiftType);
        $proxy->setAttribute('date', Carbon::parse($shift->date->toDateString()));

        return $proxy;
    }

    private function safeRoute(string $name): ?string {
        try {
            return route($name);
        } catch (\Throwable) {
            return null;
        }
    }
}
