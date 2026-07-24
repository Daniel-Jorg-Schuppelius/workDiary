<?php
/*
 * Created on   : Mon Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalendlyIngestService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Calendly\Services;

use App\Models\{AppointmentRequest, IntegrationInboxItem, Organization};
use App\Plugins\Calendly\CalendlyPlugin;
use Carbon\CarbonImmutable;

/**
 * Zustandsautomat der Calendly-Terminwünsche (Feature 095). Verarbeitet einen
 * Webhook-Payload bzw. einen Backfill-Datensatz idempotent (Upsert per
 * Invitee-URI) und hält die 087-Zweiphasigkeit ein: ein Termin landet als
 * `requested` und wird NIE automatisch zum Dispositionseintrag.
 *
 * Reschedule = zwei Calendly-Events (`invitee.canceled` alt + `invitee.created`
 * neu); die Verlinkung läuft über URI-Strings und funktioniert in beiden
 * Ankunftsreihenfolgen. Unzuordenbare Invitees landen in der Zuordnungs-Inbox.
 */
class CalendlyIngestService {
    public function __construct(
        private readonly CalendlyAppointmentMatcher $matcher,
        private readonly CalendlyConfirmService $confirm,
    ) {}

    /**
     * Einstieg aus dem Webhook-Job.
     *
     * @param  array<string, mixed>  $payload  Top-Level-Webhook-Payload (event + payload)
     */
    public function handlePayload(Organization $organization, array $payload): ?AppointmentRequest {
        $event = (string) ($payload['event'] ?? '');
        $invitee = is_array($payload['payload'] ?? null) ? $payload['payload'] : [];

        return $this->handleInvitee($organization, $event, $invitee);
    }

    /**
     * Einstieg aus dem Backfill (Event-URI + Invitee-Resource).
     *
     * @param  array<string, mixed>  $invitee
     */
    public function handleInvitee(Organization $organization, string $event, array $invitee): ?AppointmentRequest {
        $inviteeUri = (string) ($invitee['uri'] ?? '');
        if ($inviteeUri === '') {
            return null;
        }

        // Backfill liefert kein Event; aus dem Invitee-Status ableiten.
        if ($event === '') {
            $event = ((string) ($invitee['status'] ?? 'active')) === 'canceled' ? 'invitee.canceled' : 'invitee.created';
        }

        return match ($event) {
            'invitee.created' => $this->handleCreated($organization, $invitee, $inviteeUri),
            'invitee.canceled' => $this->handleCanceled($organization, $invitee, $inviteeUri),
            default => null,
        };
    }

    /** @param array<string, mixed> $invitee */
    private function handleCreated(Organization $organization, array $invitee, string $inviteeUri): AppointmentRequest {
        $fields = $this->mapInviteeFields($invitee);
        $oldInvitee = $this->uri($invitee['old_invitee'] ?? null);

        $request = $this->find($organization, $inviteeUri);
        $isNew = ! $request instanceof AppointmentRequest;

        if ($isNew) {
            $request = new AppointmentRequest([
                'organization_id' => $organization->id,
                'source' => AppointmentRequest::SOURCE_CALENDLY,
                'source_uri' => $inviteeUri,
                'status' => AppointmentRequest::STATUS_REQUESTED,
            ]);
        }
        /** @var AppointmentRequest $request */
        $request->fill($fields);

        if ($request->customer_id === null) {
            $request->customer_id = $this->matcher->matchCustomer($organization, $request->invitee_email, $request->invitee_name)?->id;
        }
        if ($request->assigned_user_id === null) {
            $scheduledEvent = is_array($invitee['scheduled_event'] ?? null) ? $invitee['scheduled_event'] : [];
            $request->assigned_user_id = $this->matcher->matchHostUser($organization, $scheduledEvent);
        }

        if ($oldInvitee !== null) {
            $request->is_reschedule = true;
            $request->rescheduled_from_uri = $oldInvitee;
            $predecessor = $this->find($organization, $oldInvitee);
            if ($predecessor instanceof AppointmentRequest) {
                $request->customer_id ??= $predecessor->customer_id;
                $request->assigned_user_id ??= $predecessor->assigned_user_id;
                $request->lead_id ??= $predecessor->lead_id;
                $this->supersede($predecessor, $inviteeUri);
            }
        }

        $request->save();

        if ($isNew && $request->customer_id === null) {
            $this->recordUnmatched($organization, $request, $invitee);
        }

        return $request;
    }

    /** @param array<string, mixed> $invitee */
    private function handleCanceled(Organization $organization, array $invitee, string $inviteeUri): AppointmentRequest {
        $rescheduled = (bool) ($invitee['rescheduled'] ?? false);
        $newInvitee = $this->uri($invitee['new_invitee'] ?? null);
        $cancellation = is_array($invitee['cancellation'] ?? null) ? $invitee['cancellation'] : null;
        $targetStatus = $rescheduled ? AppointmentRequest::STATUS_SUPERSEDED : AppointmentRequest::STATUS_CANCELED;

        $request = $this->find($organization, $inviteeUri);
        if (! $request instanceof AppointmentRequest) {
            // Cancel vor Create (selten): Stub im Zielzustand anlegen.
            $request = new AppointmentRequest([
                'organization_id' => $organization->id,
                'source' => AppointmentRequest::SOURCE_CALENDLY,
                'source_uri' => $inviteeUri,
            ]);
            $request->fill($this->mapInviteeFields($invitee));
        }

        $request->status = $targetStatus;
        $request->cancellation = $cancellation;
        if ($rescheduled && $newInvitee !== null) {
            $request->rescheduled_to_uri = $newInvitee;
        }
        $request->save();

        // Dispositionseintrag freigeben (echter Storno via OrderService, guard-geschützt).
        $reason = is_string($cancellation['reason'] ?? null) ? (string) $cancellation['reason'] : null;
        $this->confirm->release($request, $reason);

        return $request;
    }

    /** Markiert den Vorgänger einer Umbuchung als abgelöst und gibt seinen Dispositionseintrag frei. */
    private function supersede(AppointmentRequest $predecessor, string $newInviteeUri): void {
        if (in_array($predecessor->status, [AppointmentRequest::STATUS_SUPERSEDED, AppointmentRequest::STATUS_CANCELED], true)) {
            return;
        }
        $predecessor->status = AppointmentRequest::STATUS_SUPERSEDED;
        $predecessor->rescheduled_to_uri = $newInviteeUri;
        $predecessor->save();

        $this->confirm->release($predecessor, (string) __('Umbuchung'));
    }

    private function find(Organization $organization, string $inviteeUri): ?AppointmentRequest {
        return AppointmentRequest::query()
            ->where('organization_id', $organization->id)
            ->where('source', AppointmentRequest::SOURCE_CALENDLY)
            ->where('source_uri', $inviteeUri)
            ->first();
    }

    /**
     * Reine Feldabbildung Invitee-Payload → appointment_requests (ohne Matching/Status).
     *
     * @param  array<string, mixed>  $invitee
     * @return array<string, mixed>
     */
    private function mapInviteeFields(array $invitee): array {
        $scheduledEvent = is_array($invitee['scheduled_event'] ?? null) ? $invitee['scheduled_event'] : [];
        $location = is_array($scheduledEvent['location'] ?? null) ? $scheduledEvent['location'] : [];

        return [
            'start_at' => $this->time($scheduledEvent['start_time'] ?? null),
            'end_at' => $this->time($scheduledEvent['end_time'] ?? null),
            'invitee_timezone' => $this->str($invitee['timezone'] ?? null),
            'invitee_name' => $this->str($invitee['name'] ?? null),
            'invitee_email' => $this->str($invitee['email'] ?? null),
            'service_label' => $this->str($scheduledEvent['name'] ?? null),
            'location_type' => $this->str($location['type'] ?? null),
            'location' => $this->str($location['location'] ?? null),
            'join_url' => $this->str($location['join_url'] ?? null),
            'cancel_url' => $this->str($invitee['cancel_url'] ?? null),
            'reschedule_url' => $this->str($invitee['reschedule_url'] ?? null),
            'questions_and_answers' => is_array($invitee['questions_and_answers'] ?? null) ? $invitee['questions_and_answers'] : null,
            'tracking' => is_array($invitee['tracking'] ?? null) ? $invitee['tracking'] : null,
        ];
    }

    /** @param array<string, mixed> $invitee */
    private function recordUnmatched(Organization $organization, AppointmentRequest $request, array $invitee): void {
        IntegrationInboxItem::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'plugin_id' => CalendlyPlugin::ID,
                'dedupe_key' => 'calendly_invitee:' . (string) $request->source_uri,
            ],
            [
                'source' => 'api',
                'target_type' => (new AppointmentRequest)->getMorphClass(),
                'external_type' => 'calendly_invitee',
                'external_id' => (string) $request->source_uri,
                'group_key' => $request->service_label ?? 'calendly',
                'case_type' => IntegrationInboxItem::CASE_UNMATCHED,
                'status' => IntegrationInboxItem::STATUS_OPEN,
                'referenceable_type' => $request->getMorphClass(),
                'referenceable_id' => $request->getKey(),
                'remote_snapshot' => $invitee,
                'display_title' => $request->invitee_name,
                'display_subtitle' => $request->invitee_email,
                'occurred_at' => now(),
            ],
        );
    }

    private function time(mixed $value): ?CarbonImmutable {
        return is_string($value) && $value !== '' ? CarbonImmutable::parse($value) : null;
    }

    private function str(mixed $value): ?string {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function uri(mixed $value): ?string {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
