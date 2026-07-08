<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ZammadOutboxDispatcher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Zammad\Services;

use App\Contracts\Integration\IntegrationOutboxDispatcher;
use App\Models\{ExternalReference, IntegrationOutboxEntry, TimeEntry, ZammadConnection};
use App\Plugins\Zammad\Contracts\ZammadGatewayFactory;
use App\Plugins\Zammad\ZammadPlugin;
use RuntimeException;

/**
 * Status-Rückkanal nach Zammad (Feature 060, 2. Ausbaustufe): überträgt die
 * Erledigung einer WorkDiary-Aufgabe als schlanke Ticket-Rückmeldung
 * (Zielstatus + interne Notiz) über die generische integration_outbox — gleiche
 * Mechanik wie der Todoist-/WebDAV-Export. Bewusst KEINE Vollsynchronisation;
 * das Ticketsystem bleibt führend. Ist für die Organisation kein Rückkanal
 * konfiguriert ({@see ZammadConnection::pushesResolution()}), ist der Eintrag
 * ein No-Op (erledigt).
 */
class ZammadOutboxDispatcher implements IntegrationOutboxDispatcher {
    public const OP_TICKET_RESOLVE = 'ticket.resolve';

    public const OP_TICKET_TIME = 'ticket.time';

    public const OP_TICKET_COMMENT = 'ticket.comment';

    public const EXT_TYPE_TIME = 'time_accounting';

    public function pluginId(): string {
        return ZammadPlugin::ID;
    }

    public function dispatch(IntegrationOutboxEntry $entry): bool {
        return match ($entry->operation) {
            self::OP_TICKET_RESOLVE => $this->resolveTicket($entry),
            self::OP_TICKET_TIME => $this->accountTime($entry),
            self::OP_TICKET_COMMENT => $this->addComment($entry),
            default => throw new RuntimeException('Unbekannte Zammad-Outbox-Operation: ' . $entry->operation),
        };
    }

    private function resolveTicket(IntegrationOutboxEntry $entry): bool {
        $payload = $entry->payload;
        $ticketId = (int) ($payload['ticket_id'] ?? 0);
        if ($ticketId <= 0) {
            return true; // ohne Ticketbezug nichts zu tun
        }

        $connection = ZammadConnection::query()->withoutGlobalScopes()
            ->where('organization_id', $entry->organization_id)
            ->get()
            ->first(static fn (ZammadConnection $c): bool => $c->pushesResolution());
        if (! $connection instanceof ZammadConnection) {
            return true; // Rückkanal (mehr) nicht konfiguriert → No-Op
        }

        $gateway = app(ZammadGatewayFactory::class)->for($connection);

        return $gateway->updateTicketState(
            $ticketId,
            $connection->resolved_state,
            (string) __('zammad.resolution.note'),
        );
    }

    /**
     * Kommentar-Rückmeldung (Feature 065, P8): legt einen Artikel am
     * Zammad-Ticket an; internal steuert die Kundensichtbarkeit (interne
     * Notizen bleiben intern — Typgarantie kommt aus der Message-Art).
     */
    private function addComment(IntegrationOutboxEntry $entry): bool {
        $payload = $entry->payload;
        $ticketId = (int) ($payload['ticket_id'] ?? 0);
        $body = trim((string) ($payload['body'] ?? ''));
        if ($ticketId <= 0 || $body === '') {
            return true;
        }

        $connection = ZammadConnection::query()->withoutGlobalScopes()
            ->where('organization_id', $entry->organization_id)
            ->where('active', true)
            ->first();
        if (! $connection instanceof ZammadConnection) {
            return true;
        }

        $gateway = app(ZammadGatewayFactory::class)->for($connection);

        if (! $gateway->addArticle($ticketId, $body, (bool) ($payload['internal'] ?? true))) {
            throw new RuntimeException('Zammad article creation failed'); // → Queue-Retry
        }

        return true;
    }

    /**
     * Bucht eine erfasste Zeit auf das verknüpfte Ticket zurück (Rang 23) —
     * idempotent über eine `time_accounting`-ExternalReference je TimeEntry.
     */
    private function accountTime(IntegrationOutboxEntry $entry): bool {
        $payload = $entry->payload;
        $ticketId = (int) ($payload['ticket_id'] ?? 0);
        $minutes = (int) ($payload['minutes'] ?? 0);
        $timeEntryId = (int) ($payload['time_entry_id'] ?? 0);
        if ($ticketId <= 0 || $minutes <= 0) {
            return true;
        }

        $connection = ZammadConnection::query()->withoutGlobalScopes()
            ->where('organization_id', $entry->organization_id)
            ->get()
            ->first(static fn (ZammadConnection $c): bool => $c->pushesTime());
        if (! $connection instanceof ZammadConnection) {
            return true; // Zeit-Rückkanal nicht konfiguriert → No-Op
        }

        // Schon gebucht? (Queue-Retry-sicher)
        if ($timeEntryId > 0 && $this->alreadyAccounted($entry->organization_id, $timeEntryId)) {
            return true;
        }

        // Einheiten-Konvention: `hour` rechnet Minuten in Stunden um.
        $value = $connection->time_unit === 'hour' ? round($minutes / 60, 2) : (float) $minutes;

        $gateway = app(ZammadGatewayFactory::class)->for($connection);
        if (! $gateway->accountTime($ticketId, $value)) {
            throw new RuntimeException('Zammad time accounting failed'); // → Queue-Retry
        }

        if ($timeEntryId > 0) {
            ExternalReference::query()->withoutGlobalScopes()->create([
                'organization_id' => $entry->organization_id,
                'plugin_id' => ZammadPlugin::ID,
                'external_type' => self::EXT_TYPE_TIME,
                'referenceable_type' => (new TimeEntry)->getMorphClass(),
                'referenceable_id' => $timeEntryId,
                'external_id' => (string) $timeEntryId,
                'payload' => ['ticket_id' => $ticketId, 'minutes' => $minutes, 'time_unit' => $connection->time_unit],
                'synced_at' => now(),
            ]);
        }

        return true;
    }

    private function alreadyAccounted(int $organizationId, int $timeEntryId): bool {
        return ExternalReference::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('plugin_id', ZammadPlugin::ID)
            ->where('external_type', self::EXT_TYPE_TIME)
            ->where('external_id', (string) $timeEntryId)
            ->exists();
    }
}
