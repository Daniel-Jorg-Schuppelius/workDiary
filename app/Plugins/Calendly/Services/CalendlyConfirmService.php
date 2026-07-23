<?php
/*
 * Created on   : Mon Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalendlyConfirmService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Calendly\Services;

use App\Enums\Diary\{DispatchStatus, Status};
use App\Exceptions\InvalidOrderTransitionException;
use App\Models\{AppointmentRequest, CalendlyConnection, DiaryEntry, DiaryEntryEvent, IntegrationInboxItem, User};
use App\Plugins\Calendly\CalendlyPlugin;
use App\Services\Diary\OrderService;
use App\Services\Dispatch\DispatchStatusResolver;
use App\Services\Event\IcsFeedService;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Zweiphasige Bestätigung eines Calendly-Terminwunsches (Feature 095): erst die
 * interne Bestätigung erzeugt den Dispositionseintrag ({@see DiaryEntry}) +
 * bestätigt die Disposition ({@see DispatchStatus::Confirmed}) und schickt dem
 * Invitee eine ICS-Bestätigung. Die Absage/Umbuchung gibt den Eintrag als
 * echten Storno über den {@see OrderService} frei — guard-geschützt: ist der
 * Auftrag schon fortgeschritten, entsteht ein Inbox-Konflikt statt Datenverlust.
 */
class CalendlyConfirmService {
    public function __construct(
        private readonly OrderService $orders,
        private readonly DispatchStatusResolver $dispatch,
        private readonly IcsFeedService $ics,
    ) {}

    /** Bestätigt einen `requested`-Terminwunsch und legt den Dispositionseintrag an. */
    public function confirm(AppointmentRequest $request, User $decider): ?DiaryEntry {
        if ($request->status !== AppointmentRequest::STATUS_REQUESTED) {
            return null;
        }

        $ownerId = $request->assigned_user_id ?? (int) $decider->id;
        $minutes = $request->start_at !== null && $request->end_at !== null
            ? (int) round(abs($request->start_at->diffInMinutes($request->end_at)))
            : null;

        $entry = new DiaryEntry;
        $entry->organization_id = $request->organization_id;
        $entry->user_id = $ownerId;
        $entry->assigned_user_id = $request->assigned_user_id;
        $entry->customer_id = $request->customer_id;
        $entry->title = $request->service_label;
        $entry->content = $this->buildContent($request);
        $entry->status = Status::Open; // = Planned
        $entry->start_at = $request->start_at;
        $entry->end_at = $request->end_at;
        $entry->scheduled_for = $request->start_at;
        $entry->time_window_start = $this->localTime($request, 'start');
        $entry->time_window_end = $this->localTime($request, 'end');
        $entry->service_minutes = $minutes;
        $entry->planned_minutes = $minutes;
        $entry->planned_at = now();
        $entry->planned_by_user_id = (int) $decider->id;
        $entry->is_archived = false;
        $this->fillAddress($entry, $request);
        $entry->save();

        $this->dispatch->transition($entry, DispatchStatus::Confirmed);

        DiaryEntryEvent::query()->create([
            'organization_id' => $entry->organization_id,
            'diary_entry_id' => $entry->id,
            'event' => 'dispatch.calendly_confirmed',
            'from_status' => $entry->status->slug(),
            'to_status' => $entry->status->slug(),
            'actor_user_id' => (int) $decider->id,
            'actor_kind' => 'user',
            'note' => (string) __('Calendly-Terminwunsch bestätigt'),
            'payload' => ['appointment_request_id' => $request->id, 'source_uri' => $request->source_uri],
            'occurred_at' => now(),
        ]);

        $request->forceFill([
            'status' => AppointmentRequest::STATUS_CONFIRMED,
            'decided_by' => (int) $decider->id,
            'decided_at' => now(),
            'diary_entry_id' => $entry->id,
        ])->save();

        $this->sendConfirmationIcs($request);

        return $entry;
    }

    /** Lehnt einen `requested`-Terminwunsch intern ab (ohne Dispositionseintrag). */
    public function decline(AppointmentRequest $request, User $decider, ?string $reason = null): void {
        if ($request->status !== AppointmentRequest::STATUS_REQUESTED) {
            return;
        }
        $request->forceFill([
            'status' => AppointmentRequest::STATUS_DECLINED,
            'decided_by' => (int) $decider->id,
            'decided_at' => now(),
            'decline_reason' => $reason,
        ])->save();
    }

    /**
     * Gibt den verknüpften Dispositionseintrag bei Absage/Umbuchung frei —
     * echter Storno über den {@see OrderService}. Guard: aus einem bereits
     * fortgeschrittenen Auftrag (erledigt/berechnet) kein Force-Storno, sondern
     * ein Inbox-Konflikt zur manuellen Klärung.
     */
    public function release(AppointmentRequest $request, ?string $reason): void {
        if ($request->diary_entry_id === null) {
            return;
        }
        $entry = DiaryEntry::query()->find($request->diary_entry_id);
        if (! $entry instanceof DiaryEntry) {
            return;
        }
        $actor = $this->resolveActor($request);
        if (! $actor instanceof User) {
            return;
        }

        try {
            $this->orders->cancel($entry, $actor, $reason ?? (string) __('Calendly-Absage'));
        } catch (InvalidOrderTransitionException) {
            $this->recordConflict($request, $entry);
        }
    }

    private function resolveActor(AppointmentRequest $request): ?User {
        if ($request->decided_by !== null) {
            $decider = User::query()->find($request->decided_by);
            if ($decider instanceof User) {
                return $decider;
            }
        }

        $connection = CalendlyConnection::query()->where('organization_id', $request->organization_id)->first();
        if ($connection instanceof CalendlyConnection && $connection->connected_by !== null) {
            $connector = User::query()->find($connection->connected_by);
            if ($connector instanceof User) {
                return $connector;
            }
        }

        return User::query()->where('organization_id', $request->organization_id)->first();
    }

    private function recordConflict(AppointmentRequest $request, DiaryEntry $entry): void {
        IntegrationInboxItem::query()->updateOrCreate(
            [
                'organization_id' => $request->organization_id,
                'plugin_id' => CalendlyPlugin::ID,
                'dedupe_key' => 'calendly_cancel_conflict:' . (string) $request->source_uri,
            ],
            [
                'source' => 'api',
                'target_type' => (new DiaryEntry)->getMorphClass(),
                'external_type' => 'calendly_cancel_conflict',
                'external_id' => (string) $request->source_uri,
                'case_type' => IntegrationInboxItem::CASE_CONFLICT,
                'status' => IntegrationInboxItem::STATUS_OPEN,
                'referenceable_type' => $entry->getMorphClass(),
                'referenceable_id' => $entry->getKey(),
                'remote_snapshot' => ['appointment_request_id' => $request->id, 'diary_entry_id' => $entry->id, 'reason' => (string) __('Calendly-Absage nach Auftragsbeginn')],
                'display_title' => $request->invitee_name ?? (string) __('Calendly-Absage'),
                'display_subtitle' => (string) __('Auftrag bereits in Bearbeitung — bitte manuell prüfen.'),
                'occurred_at' => now(),
            ],
        );
    }

    private function sendConfirmationIcs(AppointmentRequest $request): void {
        $email = (string) $request->invitee_email;
        if ($email === '' || $request->start_at === null) {
            return;
        }

        try {
            $document = $this->ics->documentForAppointment($request);
            Mail::raw((string) __('Ihr Termin ist bestätigt.'), function ($message) use ($email, $document): void {
                $message->to($email)
                    ->subject((string) __('Terminbestätigung'))
                    ->attachData($document, 'termin.ics', ['mime' => 'text/calendar; charset=utf-8; method=PUBLISH']);
            });
        } catch (Throwable) {
            // ICS-Versand ist Best-Effort — Bestätigung bleibt gültig.
        }
    }

    private function buildContent(AppointmentRequest $request): string {
        $parts = array_filter([
            $request->service_label,
            $request->invitee_name,
            $request->invitee_email,
        ], fn(?string $value): bool => $value !== null && $value !== '');

        $content = trim('Calendly: ' . implode(' – ', $parts));

        return $content !== 'Calendly:' ? $content : (string) __('Calendly-Termin');
    }

    private function localTime(AppointmentRequest $request, string $which): ?string {
        $value = $which === 'start' ? $request->start_at : $request->end_at;

        return $value?->copy()->timezone((string) config('app.timezone', 'Europe/Berlin'))->format('H:i');
    }

    private function fillAddress(DiaryEntry $entry, AppointmentRequest $request): void {
        $customer = $request->customer;
        if ($customer === null) {
            return;
        }
        $entry->address_line = $customer->getAttribute('address_street') ?: $customer->getAttribute('address');
        $entry->address_zip = $customer->getAttribute('address_zip');
        $entry->address_city = $customer->getAttribute('address_city');
    }
}
