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

use App\Plugins\Support\PluginApiException;
use App\Plugins\Zammad\Contracts\ZammadGateway;
use App\Plugins\Zammad\Models\ZammadConnection;
use App\Support\UrlSafety;
use Throwable;
use ZammadAPIClient\Core\{Auth, ConnectionConfig};
use ZammadAPIClient\Exceptions\{AuthenticationException, BadRequestException, ForbiddenException, NotFoundException, RateLimitException, ServerErrorException, ValidationException, ZammadException};
use ZammadAPIClient\ZammadClient;

/**
 * Adapter auf den offiziellen `zammad/zammad-api-client-php` 3.x (Feature 060,
 * MVP-129). Token-Auth über die Basis-URL der Anbindung (`/api/v1` hängt das
 * SDK an). Bewusst dünn: normalisiert Tickets in einfache Arrays, damit die
 * Importlogik providerneutral und testbar bleibt.
 */
class ZammadClientGateway implements ZammadGateway {
    public function __construct(private readonly ZammadClient $client) {}

    public static function forConnection(ZammadConnection $connection): self {
        // SSRF-Schutz (Whitebox-Befund 2026-07): öffentlich routbar, außer die
        // Anbindung gibt private Adressen frei und der Betreiber lässt das zu.
        UrlSafety::assertAcceptableExternalBaseUrl(
            (string) $connection->base_url,
            $connection->allowsPrivateNetwork(),
            'Zammad',
            (string) __('zammad::zammad.guard.subject'),
            (string) __('zammad::zammad.guard.private_hint'),
        );

        // Das SDK schickt den Token nicht über http:// an fremde Hosts; im freigegebenen LAN ist http üblich.
        return new self(ZammadClient::create(
            (string) $connection->base_url,
            Auth::token((string) $connection->api_token),
            new ConnectionConfig(allowInsecureHttp: $connection->allowsPrivateNetwork()),
        ));
    }

    public function listTickets(?int $groupId = null, int $page = 1, int $perPage = 100): array {
        // expand=true liefert Status, Kunde und Organisation als Klarwerte; das DTO legt sie in customFields ab.
        $tickets = $this->client->ticket()->allPage($page, $perPage, ['expand' => 'true']);

        $out = [];
        foreach ($tickets as $ticket) {
            if ($ticket->id === null) {
                continue;
            }
            if ($groupId !== null && $ticket->group_id !== $groupId) {
                continue;
            }
            $state = $ticket->customFields['state'] ?? null;
            $out[] = [
                'id' => $ticket->id,
                'number' => (string) $ticket->number,
                'title' => $ticket->title,
                'group_id' => $ticket->group_id,
                'state' => is_string($state) && $state !== '' ? $state : null,
                'customer_id' => $ticket->customer_id,
                // Für den Kundenvorschlag (Rang 21).
                'customer' => $this->stringValue($ticket->customFields['customer'] ?? null),
                'organization' => $this->stringValue($ticket->customFields['organization'] ?? null),
            ];
        }

        return $out;
    }

    /**
     * Health-Ping: eine Seite mit einem Ticket abrufen. Eine Antwort (auch leer)
     * heißt „erreichbar + Token gültig"; HTTP-Fehler werden zur PluginApiException
     * mit Status, Netzfehler bleiben die Exception des SDK.
     */
    public function probe(): void {
        try {
            $this->client->ticket()->allPage(1, 1);
        } catch (ZammadException $e) {
            $status = self::httpStatus($e);
            if ($status === 0) {
                throw $e;
            }

            throw new PluginApiException(sprintf('Zammad /tickets antwortete mit HTTP %d.', $status), $status, '/tickets', previous: $e);
        }
    }

    public function updateTicketState(int $ticketId, ?string $state, ?string $note): bool {
        $changes = [];
        if ($state !== null && $state !== '') {
            $changes['state'] = $state;
        }
        if ($note !== null && $note !== '') {
            // Interne Notiz als `article`-Objekt am Ticket-Update — keine
            // Kundenkommunikation aus WorkDiary heraus (internal = true).
            $changes['article'] = [
                'body' => $note,
                'type' => 'note',
                'internal' => true,
            ];
        }
        if ($changes === []) {
            return true; // nichts zu tun
        }

        try {
            $this->client->ticket()->patch($ticketId, $changes);
        } catch (Throwable) {
            return false;
        }

        return true;
    }

    public function accountTime(int $ticketId, float $timeUnit): bool {
        // Das SDK kennt keine TimeAccounting-Ressource → roher, authentifizierter POST.
        try {
            $this->client->getHandler()->post("tickets/{$ticketId}/time_accountings", [
                'ticket_id' => $ticketId,
                'time_unit' => (string) $timeUnit,
            ]);
        } catch (Throwable) {
            return false;
        }

        return true;
    }

    /** Die Exceptions des SDK tragen keinen Status; die Klasse steht für ihn. */
    private static function httpStatus(ZammadException $e): int {
        return match (true) {
            $e instanceof BadRequestException => 400,
            $e instanceof AuthenticationException => 401,
            $e instanceof ForbiddenException => 403,
            $e instanceof NotFoundException => 404,
            $e instanceof ValidationException => 422,
            $e instanceof RateLimitException => 429,
            $e instanceof ServerErrorException => 500,
            default => 0,
        };
    }

    /** Expandierter Zammad-Wert (customer-Login/organization-Name) als String. */
    private function stringValue(mixed $value): string {
        return is_string($value) ? trim($value) : '';
    }
}
