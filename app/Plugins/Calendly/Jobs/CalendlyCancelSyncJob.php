<?php
/*
 * Created on   : Wed Jul 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalendlyCancelSyncJob.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Calendly\Jobs;

use App\Models\{AppointmentRequest, CalendlyConnection};
use App\Plugins\Calendly\CalendlyPlugin;
use App\Plugins\Calendly\Services\CalendlyOutboundService;
use App\Plugins\PluginErrorRecorder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use RuntimeException;
use Throwable;

/**
 * Cancel-Sync Richtung Calendly (Feature 095, P5): wird ein bestätigter
 * Calendly-Termin app-seitig storniert, sagt dieser Job den Termin best effort
 * auch bei Calendly ab (`POST /scheduled_events/{uuid}/cancellation`). Fehler
 * blockieren den App-Storno NIE — sie landen im {@see PluginErrorRecorder}
 * (Inbox-UI). Erst nach erfolgreicher Absage wechselt der Terminwunsch auf
 * `canceled`; schlägt sie fehl, bleibt er `confirmed` (der Termin ist bei
 * Calendly ja noch aktiv) und der Backfill hält beide Seiten konsistent.
 */
class CalendlyCancelSyncJob implements ShouldQueue {
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $appointmentRequestId,
        public readonly ?string $reason = null,
    ) {}

    public function handle(CalendlyOutboundService $outbound, PluginErrorRecorder $errors): void {
        // Queue-Kontext hat keine Org-Bindung → bewusst ohne Global Scopes.
        $request = AppointmentRequest::query()->withoutGlobalScopes()->find($this->appointmentRequestId);
        if (
            ! $request instanceof AppointmentRequest
            || $request->source !== AppointmentRequest::SOURCE_CALENDLY
            || $request->status !== AppointmentRequest::STATUS_CONFIRMED
        ) {
            return; // inzwischen anderweitig abgeschlossen (z. B. Calendly-seitige Absage)
        }
        $organizationId = (int) $request->organization_id;

        $connection = CalendlyConnection::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->first();
        if (! $connection instanceof CalendlyConnection || ! $connection->isActive()) {
            return; // bewusst getrennt → nichts abzugleichen
        }

        $eventUuid = CalendlyOutboundService::eventUuidFromUri((string) $request->source_uri);
        if ($eventUuid === null) {
            $errors->record(CalendlyPlugin::ID, 'outbound-cancel', new RuntimeException(
                'Event-UUID nicht aus der Invitee-URI ermittelbar — Termin bei Calendly manuell absagen.',
            ), ['appointment_request_id' => $request->id, 'source_uri' => (string) $request->source_uri], $organizationId);

            return;
        }

        try {
            $canceled = $outbound->cancel($connection, $eventUuid, $this->reason);
        } catch (Throwable $e) {
            $errors->record(CalendlyPlugin::ID, 'outbound-cancel', $e, [
                'appointment_request_id' => $request->id,
                'event_uuid' => $eventUuid,
            ], $organizationId);

            return;
        }

        if (! $canceled) {
            $errors->record(CalendlyPlugin::ID, 'outbound-cancel', new RuntimeException(
                'Calendly-Absage fehlgeschlagen — Termin bei Calendly manuell absagen.',
            ), ['appointment_request_id' => $request->id, 'event_uuid' => $eventUuid], $organizationId);

            return;
        }

        // Idempotent zum später eintreffenden invitee.canceled-Echo-Webhook.
        $request->forceFill([
            'status' => AppointmentRequest::STATUS_CANCELED,
            'cancellation' => ['canceler_type' => 'host', 'reason' => $this->reason],
        ])->save();
        $request->audit('calendly.cancel_synced', ['event_uuid' => $eventUuid]);
    }
}
