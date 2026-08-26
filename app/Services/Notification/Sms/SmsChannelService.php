<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SmsChannelService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Notification\Sms;

use App\Enums\Notification\{NotificationChannel, NotificationEvent, SmsDeliveryStatus};
use App\Jobs\Notification\SmsDeliveryJob;
use App\Models\Notification\{NotificationDispatchLog, NotificationRule};
use App\Models\{Organization, User};
use App\Plugins\Contracts\SmsProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * SMS-Kanal der Benachrichtigungsmatrix (Feature 147, MVP-730).
 *
 * Fünf Tore stehen vor jedem Versand, und jedes hat einen eigenen Grund:
 *
 *  1. **Ereignis kritisch?** ({@see NotificationEvent::supportsSms()}) — SMS
 *     ist der Alarmierungsweg ohne Datenverbindung, nicht der Kanal für
 *     Fristenpost.
 *  2. **Kanal in der Org-Regel gewählt?** — nichts ist Default, SMS kostet.
 *  3. **Opt-in der Person mit bestätigter Nummer?** — ohne Einwilligung kein
 *     Versand (Art. 6 DSGVO); die Rufnummer verlässt sonst die Plattform.
 *  4. **Gateway aktiviert?** — anbieterneutral über ein Plugin je Organisation.
 *  5. **Budget frei?** — Deckel je Monat, sonst kann eine Fehlkonfiguration
 *     eine unbegrenzte Rechnung erzeugen.
 *
 * Erst danach entsteht die Dispatch-Log-Zeile (`sms:<userId>`) — sie ist
 * zugleich Nachweis, Statusträger und Doppelversand-Schutz: schlägt der
 * Unique-Key an, war diese Alarmierung für diese Person schon draußen.
 */
class SmsChannelService {
    public function __construct(
        private readonly SmsProviderResolver $providers,
        private readonly SmsOptInService $optIn,
        private readonly SmsBudgetService $budget,
    ) {}

    /**
     * Stellt eine Benachrichtigung als SMS zu (asynchron über
     * {@see SmsDeliveryJob}). Rückgabe: wurde ein Versand angestoßen?
     *
     * Fehler hier dürfen den übrigen Versand (In-App/Mail/Push) nie
     * scheitern lassen — außerhalb von Tests wird geloggt statt geworfen.
     *
     * @param  array<string, mixed>  $payload
     */
    public function send(User $recipient, NotificationEvent $event, Model $subject, array $payload, string $stage): bool {
        try {
            return $this->dispatch($recipient, $event, $subject, $payload, $stage);
        } catch (Throwable $e) {
            if (app()->runningUnitTests()) {
                throw $e;
            }
            Log::warning('sms: dispatch failed', [
                'event' => $event->value,
                'user_id' => $recipient->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /** @param  array<string, mixed>  $payload */
    private function dispatch(User $recipient, NotificationEvent $event, Model $subject, array $payload, string $stage): bool {
        if (! $event->supportsSms()) {
            return false;
        }

        $organization = $recipient->organization;
        if (! $organization instanceof Organization) {
            return false;
        }

        $rule = NotificationRule::resolveFor((int) $organization->id, $event);
        if (! $rule->enabled || ! $rule->usesChannel(NotificationChannel::Sms)) {
            return false;
        }

        if (! $this->optIn->hasOptIn($recipient) || ! $this->providers->hasGateway($organization)) {
            return false;
        }

        $text = SmsText::forEvent($event, $payload);
        if ($text === '') {
            return false;
        }

        $log = $this->claim($organization, $recipient, $event, $subject, $stage);
        if (! $log instanceof NotificationDispatchLog) {
            return false; // bereits alarmiert — keine zweite SMS, keine zweiten Kosten
        }

        // Budgetdeckel: als eigene Entscheidung protokollieren (blocked), damit
        // im Nachweis steht, WARUM die Alarmierung nicht per SMS ging.
        if (! $this->budget->allows($organization)) {
            $log->forceFill([
                'status' => SmsDeliveryStatus::Blocked,
                'error_code' => 'budget_exceeded',
                'status_at' => Carbon::now(),
            ])->save();

            Log::warning('sms: monthly budget exhausted', ['organization_id' => $organization->id]);

            return false;
        }

        SmsDeliveryJob::dispatch((int) $log->id, (int) $recipient->id, $text);

        return true;
    }

    /**
     * Führt den Versand aus (aus dem Job heraus): Gateway holen, senden,
     * Status und Verbrauch festhalten, Audit OHNE Nachrichteninhalt.
     */
    public function deliver(NotificationDispatchLog $log, User $recipient, string $text): void {
        $organization = $recipient->organization;
        $number = $this->optIn->verifiedNumberFor($recipient);
        $provider = $organization instanceof Organization ? $this->providers->forOrganization($organization) : null;

        // Zwischen Einreihen und Zustellung kann sich alles geändert haben
        // (Opt-in widerrufen, Gateway deaktiviert) — dann gilt der neue Stand.
        if (! $organization instanceof Organization || $number === null || ! $provider instanceof SmsProvider) {
            $this->record($log, SmsDeliveryStatus::Blocked, null, 0, 'opt_in_revoked', null);

            return;
        }

        $result = $provider->sendSms($organization, $number, $text, (string) $log->id);
        $this->record($log, $result->status, $result->providerMessageId, $result->segments, $result->errorCode, $provider->smsProviderId());

        if ($result->status->isBillable()) {
            $this->budget->noteUsage($organization, max(1, $result->segments));
        }

        // Nachweis: wer, welches Ereignis, welcher Anbieter, welcher Status —
        // nie der Text und nie die Rufnummer (Feature 147, Datenminimierung).
        $recipient->audit('sms.sent', [
            'event' => (string) $log->event,
            'provider' => $provider->smsProviderId(),
            'status' => $result->status->value,
            'segments' => $result->segments,
            'error_code' => $result->errorCode,
        ]);
    }

    /**
     * Claim über den Unique-Key des Dispatch-Logs: genau ein Prozess gewinnt.
     * Eine bereits vorhandene Zeile heißt „schon versendet" — auch bei
     * parallelen Scanner-Läufen.
     */
    private function claim(Organization $organization, User $recipient, NotificationEvent $event, Model $subject, string $stage): ?NotificationDispatchLog {
        $smsStage = NotificationDispatchLog::smsStageFor((int) $recipient->id, $stage);

        $exists = NotificationDispatchLog::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('event', $event->value)
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->where('stage', $smsStage)
            ->exists();

        if ($exists) {
            return null;
        }

        try {
            return NotificationDispatchLog::query()->create([
                'organization_id' => $organization->id,
                'event' => $event->value,
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'stage' => $smsStage,
                'channel' => NotificationDispatchLog::CHANNEL_SMS,
                'recipient_count' => 1,
                'recipient_user_id' => $recipient->id,
                'segments' => 0,
            ]);
        } catch (Throwable) {
            return null; // paralleler Lauf war schneller
        }
    }

    private function record(NotificationDispatchLog $log, SmsDeliveryStatus $status, ?string $messageId, int $segments, ?string $errorCode, ?string $provider): void {
        $log->forceFill([
            'status' => $status,
            'provider' => $provider,
            'provider_message_id' => $messageId,
            'segments' => $status->isBillable() ? max(1, $segments) : 0,
            'error_code' => $errorCode,
            'status_at' => Carbon::now(),
        ])->save();
    }
}
