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

use App\Enums\Diary\Status;
use App\Models\{AppointmentRequest, BookableService, Customer, DiaryEntry, User};
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
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
            'status' => AppointmentRequest::STATUS_REQUESTED,
            'customer_id' => $customer->id,
            'portal_user_id' => $portalUser->id,
            'bookable_service_id' => $service->id,
            'start_at' => $start,
            'end_at' => $start->addMinutes($service->duration_minutes),
            'invitee_name' => $portalUser->name,
            'invitee_email' => $portalUser->email,
            'service_label' => $service->title,
        ]);
        $request->audit('appointment.requested', ['service' => $service->title]);

        return $request;
    }

    /** Bestätigung durch die Disposition — erst hier entsteht der Eintrag. */
    public function confirm(AppointmentRequest $request, User $decider): DiaryEntry {
        if ($request->status !== AppointmentRequest::STATUS_REQUESTED) {
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
        $entry->scheduled_for = $request->start_at;
        $entry->save();

        $request->forceFill([
            'status' => AppointmentRequest::STATUS_CONFIRMED,
            'diary_entry_id' => $entry->id,
            'decided_by' => $decider->id,
            'decided_at' => Carbon::now(),
        ])->save();
        $request->audit('appointment.confirmed', ['diary_entry_id' => $entry->id]);
        $this->notifyInvitee($request);

        return $entry;
    }

    public function decline(AppointmentRequest $request, User $decider, string $reason): AppointmentRequest {
        if ($request->status !== AppointmentRequest::STATUS_REQUESTED) {
            throw new RuntimeException((string) __('Diese Anfrage ist bereits entschieden.'));
        }

        $request->forceFill([
            'status' => AppointmentRequest::STATUS_DECLINED,
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
    private function notifyInvitee(AppointmentRequest $request): void {
        $email = trim((string) $request->invitee_email);
        if ($email === '') {
            return;
        }

        try {
            \Illuminate\Support\Facades\Mail::to($email)->send(new \App\Mail\AppointmentDecisionMail($request->fresh() ?? $request));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Termin-Entscheidungs-Mail fehlgeschlagen.', [
                'appointment_request_id' => $request->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Storno durch den Kunden — nur innerhalb der Frist und nur die eigene,
     * noch offene oder bestätigte Anfrage.
     */
    public function cancelFromPortal(AppointmentRequest $request, User $portalUser): AppointmentRequest {
        if ($request->portal_user_id !== $portalUser->id) {
            throw new RuntimeException((string) __('Diese Anfrage gehört nicht zu Ihrem Zugang.'));
        }
        if (! in_array($request->status, [AppointmentRequest::STATUS_REQUESTED, AppointmentRequest::STATUS_CONFIRMED], true)) {
            throw new RuntimeException((string) __('Diese Anfrage lässt sich nicht mehr stornieren.'));
        }

        $cancelHours = (int) ($request->bookableService->cancel_hours ?? 24);
        if ($request->start_at !== null && Carbon::now()->addHours($cancelHours)->greaterThan($request->start_at)) {
            throw new RuntimeException((string) __('Die Stornofrist von :hours Stunden ist unterschritten — bitte rufen Sie uns an.', ['hours' => $cancelHours]));
        }

        $request->forceFill([
            'status' => AppointmentRequest::STATUS_CANCELED,
            'cancellation' => ['by' => 'portal', 'at' => Carbon::now()->toIso8601String()],
        ])->save();
        $request->audit('appointment.canceled', ['by' => 'portal']);

        return $request;
    }
}
