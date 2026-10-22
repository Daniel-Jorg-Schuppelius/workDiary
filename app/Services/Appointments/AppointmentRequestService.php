<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AppointmentRequestService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Appointments;

use App\Enums\Calendar\AppointmentRequestStatus;
use App\Enums\Diary\Status;
use App\Enums\Notification\NotificationEvent;
use App\Exceptions\InvalidOrderTransitionException;
use App\Models\Calendar\AppointmentRequest;
use App\Models\Customer\Customer;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Models\Sales\BookableService;
use App\Services\Diary\OrderService;
use App\Services\Notification\NotificationDispatcher;
use App\Support\Tz;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Quellneutraler Lebenszyklus der Terminanfragen (Feature 087, MVP-667):
 * Portal-Anfrage → Disposition bestätigt/lehnt ab → erst die Bestätigung
 * erzeugt den Dispositions-Eintrag.
 *
 * **Die Zweiphasigkeit ist nicht verhandelbar** — eine Direktbuchung wäre
 * ein Schreibzugriff Externer auf den Dienstplan. Deshalb gibt es hier
 * keinen Weg von `requested` direkt in den Dienstplan ohne `decided_by`.
 */
class AppointmentRequestService {
    public function __construct(
        private readonly NotificationDispatcher $notifier,
        private readonly OrderService $orders,
    ) {}

    /** Portal-Anfrage anlegen (Status requested — nie mehr). */
    public function requestFromPortal(
        BookableService $service,
        Customer $customer,
        User $portalUser,
        CarbonImmutable $start,
    ): AppointmentRequest {
        if (! $service->active) {
            throw new RuntimeException((string) __('Diese Leistungsart ist nicht buchbar.'));
        }
        if ($start->lessThan($service->earliestStart())) {
            throw new RuntimeException((string) __('Dieser Termin unterschreitet den Vorlauf von :hours Stunden.', ['hours' => $service->lead_time_hours]));
        }

        $request = AppointmentRequest::query()->create([
            'organization_id' => $service->organization_id,
            'source' => AppointmentRequest::SOURCE_PORTAL,
            'source_uri' => 'portal:' . $portalUser->id . ':' . $start->format('YmdHi'),
            'status' => AppointmentRequestStatus::Requested,
            'customer_id' => $customer->id,
            'portal_user_id' => $portalUser->id,
            'bookable_service_id' => $service->id,
            'start_at' => $start->utc(),
            'end_at' => $start->utc()->addMinutes($service->duration_minutes),
            'invitee_name' => $portalUser->name,
            'invitee_email' => $portalUser->email,
            'service_label' => $service->title,
        ]);
        $request->audit('appointment.requested', ['service' => $service->title]);
        $this->announce($request);

        return $request;
    }

    /** Neue Anfrage an die Disposition melden — auch aus Plugins wie Calendly (MVP-1286). */
    public function announce(AppointmentRequest $request): void {
        $this->notifyContractor(NotificationEvent::AppointmentRequested, 'appointment.notification.requested_title', $request, $request->customer, null);
    }

    /**
     * Ersatztermin vorschlagen (MVP-1287): gleiche Dauer, der Kunde nimmt im
     * Portal an oder lehnt ab. Ein neuer Vorschlag ersetzt den offenen.
     */
    public function proposeAlternative(AppointmentRequest $request, User $decider, CarbonImmutable $start, ?string $note = null): AppointmentRequest {
        if ($start->isPast()) {
            throw new RuntimeException((string) __('appointment.proposal.in_past'));
        }

        // Gesperrt: ein gleichzeitiges Bestätigen darf den Vorschlag nicht überholen.
        $request = DB::transaction(function () use ($request, $decider, $start, $note): AppointmentRequest {
            $locked = AppointmentRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== AppointmentRequestStatus::Proposed && ! $locked->status->canTransitionTo(AppointmentRequestStatus::Proposed)) {
                throw new RuntimeException((string) __('Diese Anfrage ist bereits entschieden.'));
            }
            $minutes = $locked->start_at !== null && $locked->end_at !== null
                ? (int) $locked->start_at->diffInMinutes($locked->end_at)
                : (int) ($locked->bookableService->duration_minutes ?? 60);

            $locked->forceFill([
                'status' => AppointmentRequestStatus::Proposed,
                'proposed_start_at' => $start->utc(),
                'proposed_end_at' => $start->utc()->addMinutes(max(1, $minutes)),
                'proposal_note' => $note,
                'proposer_user_id' => $decider->id,
            ])->save();
            $locked->audit('appointment.proposed', ['start' => $start->toIso8601String()]);

            return $locked;
        });
        $this->notifyInvitee($request);

        return $request;
    }

    /** Kunde nimmt den Vorschlag an — bestätigt im Namen der vorschlagenden Person. */
    public function acceptProposal(AppointmentRequest $request, User $portalUser): DiaryEntry {
        $this->assertOwnProposal($request, $portalUser);
        $proposer = User::query()->withoutGlobalScopes()->find($request->proposer_user_id);
        if (! $proposer instanceof User) {
            throw new RuntimeException((string) __('appointment.proposal.unavailable'));
        }

        return DB::transaction(function () use ($request, $proposer): DiaryEntry {
            $request->forceFill([
                'start_at' => $request->proposed_start_at,
                'end_at' => $request->proposed_end_at,
                'proposed_start_at' => null,
                'proposed_end_at' => null,
            ])->save();
            $request->audit('appointment.proposal_accepted', ['start' => $request->start_at?->toIso8601String()]);

            return $this->confirm($request, $proposer);
        });
    }

    public function declineProposal(AppointmentRequest $request, User $portalUser): AppointmentRequest {
        $this->assertOwnProposal($request, $portalUser);

        $request->forceFill([
            'status' => AppointmentRequestStatus::Canceled,
            'cancellation' => ['by' => 'portal', 'reason' => 'proposal_declined', 'at' => Carbon::now()->toIso8601String()],
        ])->save();
        $request->audit('appointment.proposal_declined', []);
        $this->notifyContractor(NotificationEvent::AppointmentCanceled, 'appointment.notification.canceled_title', $request, $request->customer, null);

        return $request;
    }

    /** Bestätigten Termin umbuchen (MVP-1287): Anfrage und Auftrag wandern mit, der Kunde bekommt die neue Zeit. */
    public function reschedule(AppointmentRequest $request, User $actor, CarbonImmutable $start): AppointmentRequest {
        if ($request->status !== AppointmentRequestStatus::Confirmed) {
            throw new RuntimeException((string) __('appointment.reschedule.not_confirmed'));
        }
        if ($start->isPast()) {
            throw new RuntimeException((string) __('appointment.proposal.in_past'));
        }
        $minutes = $request->start_at !== null && $request->end_at !== null ? (int) $request->start_at->diffInMinutes($request->end_at) : 60;
        $from = $request->start_at?->toIso8601String();

        DB::transaction(function () use ($request, $start, $minutes): void {
            $request->forceFill(['start_at' => $start->utc(), 'end_at' => $start->utc()->addMinutes(max(1, $minutes))])->save();
            $entry = $request->diaryEntry;
            if ($entry instanceof DiaryEntry) {
                $entry->forceFill([
                    'start_at' => $request->start_at,
                    'end_at' => $request->end_at,
                    'scheduled_for' => Carbon::parse($start->setTimezone(Tz::current())->toDateString()),
                ])->save();
            }
        });
        $request->audit('appointment.rescheduled', ['from' => $from, 'to' => $request->start_at?->toIso8601String(), 'by' => $actor->id]);
        $this->notifyInvitee($request, rescheduled: true);

        return $request;
    }

    private function assertOwnProposal(AppointmentRequest $request, User $portalUser): void {
        if ($request->status !== AppointmentRequestStatus::Proposed || $request->proposed_start_at === null) {
            throw new RuntimeException((string) __('appointment.proposal.unavailable'));
        }
        if ((int) $request->customer_id !== (int) $portalUser->customer_id) {
            throw new RuntimeException((string) __('Diese Anfrage gehört nicht zu Ihrem Zugang.'));
        }
    }

    /** Bestätigung durch die Disposition — erst hier entsteht der Eintrag. */
    public function confirm(AppointmentRequest $request, User $decider): DiaryEntry {
        if (! $request->status->canTransitionTo(AppointmentRequestStatus::Confirmed)) {
            throw new RuntimeException((string) __('Diese Anfrage ist bereits entschieden.'));
        }

        $entry = new DiaryEntry;
        $entry->organization_id = $request->organization_id;
        $entry->user_id = $request->assigned_user_id ?? (int) $decider->id;
        $entry->assigned_user_id = $request->assigned_user_id;
        $entry->customer_id = $request->customer_id;
        $entry->title = $request->service_label ?? (string) __('Termin');
        $entry->content = (string) __('Portal-Terminanfrage, bestätigt durch :name.', ['name' => $decider->name]);
        $entry->status = Status::Open;
        $entry->start_at = $request->start_at;
        $entry->end_at = $request->end_at;
        // Ortsdatum des Termins, nicht das UTC-Datum.
        $entry->scheduled_for = $request->start_at !== null ? Carbon::parse($request->start_at->copy()->setTimezone(Tz::current())->toDateString()) : null;
        $entry->save();

        $request->forceFill([
            'status' => AppointmentRequestStatus::Confirmed,
            'diary_entry_id' => $entry->id,
            'decided_by' => $decider->id,
            'decided_at' => Carbon::now(),
        ])->save();
        $request->audit('appointment.confirmed', ['diary_entry_id' => $entry->id]);
        $this->notifyInvitee($request);

        return $entry;
    }

    public function decline(AppointmentRequest $request, User $decider, string $reason): AppointmentRequest {
        if (! $request->status->canTransitionTo(AppointmentRequestStatus::Declined)) {
            throw new RuntimeException((string) __('Diese Anfrage ist bereits entschieden.'));
        }

        $request->forceFill([
            'status' => AppointmentRequestStatus::Declined,
            'decided_by' => $decider->id,
            'decided_at' => Carbon::now(),
            'decline_reason' => $reason,
        ])->save();
        $request->audit('appointment.declined', ['reason' => $reason]);
        $this->notifyInvitee($request);

        return $request;
    }

    /**
     * Entscheidungs-Mail an den Anfragenden (Bestätigung mit ICS-Anhang,
     * Ablehnung mit Grund). Fehlertolerant: Ein Mail-Fehler darf die
     * Entscheidung nie zurückrollen — sie ist im Zweifel längst getroffen.
     */
    private function notifyInvitee(AppointmentRequest $request, bool $rescheduled = false): void {
        $email = trim((string) $request->invitee_email);
        if ($email === '') {
            return;
        }

        try {
            \Illuminate\Support\Facades\Mail::to($email)->send(new \App\Mail\AppointmentDecisionMail($request->fresh() ?? $request, $rescheduled));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Termin-Entscheidungs-Mail fehlgeschlagen.', [
                'appointment_request_id' => $request->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Storno durch den Kunden — nur innerhalb der Frist und nur die eigene,
     * noch offene oder bestätigte Anfrage. Ein bestätigter Termin storniert
     * seinen Auftrag mit; läuft der schon, bleibt beides stehen.
     */
    public function cancelFromPortal(AppointmentRequest $request, User $portalUser): AppointmentRequest {
        if ($request->portal_user_id !== $portalUser->id) {
            throw new RuntimeException((string) __('Diese Anfrage gehört nicht zu Ihrem Zugang.'));
        }
        // Bewusst enger als die Übergangstabelle: abgelehnte Anfragen storniert nur Calendly.
        if (! in_array($request->status, [AppointmentRequestStatus::Requested, AppointmentRequestStatus::Confirmed], true)) {
            throw new RuntimeException((string) __('Diese Anfrage lässt sich nicht mehr stornieren.'));
        }

        if (! $request->withinCancelDeadline()) {
            throw new RuntimeException((string) __('Die Stornofrist von :hours Stunden ist unterschritten — bitte rufen Sie uns an.', ['hours' => $request->cancelHours()]));
        }

        $entry = $request->diaryEntry;
        DB::transaction(function () use ($request, $portalUser, $entry): void {
            if ($entry instanceof DiaryEntry && $entry->status !== Status::Cancelled) {
                try {
                    $this->orders->cancel($entry, $portalUser, (string) __('appointment.portal.order_cancel_reason'));
                } catch (InvalidOrderTransitionException) {
                    throw new RuntimeException((string) __('appointment.portal.order_in_progress'));
                }
            }

            $request->forceFill([
                'status' => AppointmentRequestStatus::Canceled,
                'cancellation' => ['by' => 'portal', 'at' => Carbon::now()->toIso8601String()],
            ])->save();
            $request->audit('appointment.canceled', ['by' => 'portal']);
        });

        $this->notifyContractor(NotificationEvent::AppointmentCanceled, 'appointment.notification.canceled_title', $request, $request->customer, $entry?->assignedUser);

        return $request;
    }

    /** Disposition informieren; Texte werden beim Anzeigen in der Sprache der Empfänger gerendert. */
    private function notifyContractor(NotificationEvent $event, string $titleKey, AppointmentRequest $request, ?Customer $customer, ?User $affected): void {
        $service = (string) ($request->service_label ?? '');
        // Calendly-Gäste ohne Kundenzuordnung erscheinen mit ihrem Namen.
        $name = $customer->name ?? (string) ($request->invitee_name ?? '—');
        DB::afterCommit(fn () => $this->notifier->notify($event, $request, $affected, [
            'title' => (string) __($titleKey, ['customer' => $name]),
            'title_key' => $titleKey,
            'title_params' => ['customer' => $name],
            'message' => (string) __('appointment.notification.message', ['service' => $service, 'date' => $request->start_at?->copy()->setTimezone(Tz::current())->format('d.m.Y H:i')]),
            'message_key' => 'appointment.notification.message',
            'message_params' => ['service' => $service, 'date' => $request->start_at?->toIso8601String()],
            'url' => route('appointments.index'),
        ]));
    }
}
