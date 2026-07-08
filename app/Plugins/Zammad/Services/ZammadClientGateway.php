<?php
/*
 * Created on   : Sat Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ZammadClientGateway.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Zammad\Services;

use App\Models\ZammadConnection;
use App\Plugins\Zammad\Contracts\ZammadGateway;
use App\Support\UrlSafety;
use RuntimeException;
use Throwable;
use ZammadAPIClient\Client;
use ZammadAPIClient\Resource\Ticket;

/**
 * Adapter auf den offiziellen `zammad/zammad-api-client-php` (Feature 060,
 * MVP-129). Token-Auth (`Authorization: Token token=…`) über die Basis-URL der
 * Anbindung. Bewusst dünn: normalisiert Tickets in einfache Arrays, damit die
 * Importlogik providerneutral und testbar bleibt.
 */
class ZammadClientGateway implements ZammadGateway {
    public function __construct(private readonly Client $client) {}

    public static function forConnection(ZammadConnection $connection): self {
        // SSRF-Schutz: die org-konfigurierte Basis-URL muss öffentlich routbar
        // sein (kein Loopback/RFC1918/Metadata) — Whitebox-Befund 2026-07.
        if (! UrlSafety::isPubliclyRoutableHttpUrl((string) $connection->base_url)) {
            throw new RuntimeException('Zammad base_url is not a publicly routable http(s) target.');
        }

        return new self(new Client([
            'url' => $connection->base_url,
            'http_token' => $connection->api_token,
        ]));
    }

    public function listTickets(?int $groupId = null, int $page = 1, int $perPage = 100): array {
        $result = (new Ticket($this->client))->all($page, $perPage);
        $tickets = is_array($result) ? $result : [];

        $out = [];
        foreach ($tickets as $ticket) {
            if (! $ticket instanceof Ticket || $ticket->hasError()) {
                continue;
            }
            $gid = $this->intOrNull($ticket->getValue('group_id'));
            if ($groupId !== null && $gid !== $groupId) {
                continue;
            }
            $state = $ticket->getValue('state');
            $out[] = [
                'id' => (int) $ticket->getValue('id'),
                'number' => (string) $ticket->getValue('number'),
                'title' => (string) $ticket->getValue('title'),
                'group_id' => $gid,
                'state' => is_string($state) && $state !== '' ? $state : null,
                'customer_id' => $this->intOrNull($ticket->getValue('customer_id')),
                // Expandierte Klarwerte (der Client sendet immer expand=true) für
                // den Kundenvorschlag (Rang 21).
                'customer' => $this->stringValue($ticket->getValue('customer')),
                'organization' => $this->stringValue($ticket->getValue('organization')),
            ];
        }

        return $out;
    }

    /**
     * Health-Ping: eine Seite mit einem Ticket abrufen. Ein Array (auch leer)
     * heißt „erreichbar + Token gültig"; das Self-Objekt mit Fehler oder eine
     * Exception (Netz/Auth) heißt „nicht erreichbar".
     */
    public function ping(): bool {
        try {
            $result = (new Ticket($this->client))->all(1, 1);
        } catch (Throwable) {
            return false;
        }

        if ($result instanceof Ticket) {
            return ! $result->hasError();
        }

        return is_array($result);
    }

    public function updateTicketState(int $ticketId, ?string $state, ?string $note): bool {
        if (($state === null || $state === '') && ($note === null || $note === '')) {
            return true; // nichts zu tun
        }

        try {
            $ticket = (new Ticket($this->client))->get($ticketId);
            if (! $ticket instanceof Ticket || $ticket->hasError()) {
                return false;
            }

            if ($state !== null && $state !== '') {
                $ticket->setValue('state', $state);
            }
            if ($note !== null && $note !== '') {
                // Interne Notiz als `article`-Objekt am Ticket-Update — keine
                // Kundenkommunikation aus WorkDiary heraus (internal = true).
                $ticket->setValue('article', [
                    'body' => $note,
                    'type' => 'note',
                    'internal' => true,
                ]);
            }

            // save() liefert bei Erfolg die Ressource ($this), sonst false.
            return $ticket->save() !== false;
        } catch (Throwable) {
            return false;
        }
    }

    public function addArticle(int $ticketId, string $body, bool $internal = true): bool {
        // Artikel-Anlage über den rohen Endpunkt (POST /api/v1/ticket_articles)
        // — API-Fakten 2026-07 (065-Plan P8).
        try {
            $response = $this->client->post('ticket_articles', [
                'json' => [
                    'ticket_id' => $ticketId,
                    'body' => $body,
                    'type' => 'note',
                    'internal' => $internal,
                ],
            ]);

            return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
        } catch (Throwable) {
            return false;
        }
    }

    public function accountTime(int $ticketId, float $timeUnit): bool {
        // Der offizielle Client kennt keine TimeAccounting-Ressource → roher,
        // authentifizierter POST (Auth/JSON setzt der Client selbst).
        try {
            $response = $this->client->post("tickets/{$ticketId}/time_accountings", [
                'ticket_id' => $ticketId,
                'time_unit' => (string) $timeUnit,
            ]);
        } catch (Throwable) {
            return false;
        }

        if ($response->hasError()) {
            return false;
        }

        $code = (int) $response->getStatusCode();

        return $code >= 200 && $code < 300;
    }

    private function intOrNull(mixed $value): ?int {
        return is_numeric($value) ? (int) $value : null;
    }

    /** Expandierter Zammad-Wert (customer-Login/organization-Name) als String. */
    private function stringValue(mixed $value): string {
        return is_string($value) ? trim($value) : '';
    }
}
