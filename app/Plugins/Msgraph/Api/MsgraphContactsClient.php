<?php
/*
 * Created on   : Thu Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphContactsClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Msgraph\Api;

use APIToolkit\API\Authentication\OAuth2\OAuth2BearerAuthentication;
use App\Models\MsgraphContactConnection;
use App\Plugins\Msgraph\{MsgraphConfig, MsgraphPlugin};
use App\Plugins\Support\{ConnectionTokenStore, PluginApiClient, PluginHttpFactory};
use RuntimeException;
use Throwable;

/**
 * Microsoft-Graph-Kontakte-Gateway (Feature 102, Schnitt D) auf dem
 * `php-api-toolkit`-Fundament: OAuth2-Bearer über den org-gebundenen
 * {@see ConnectionTokenStore} inkl. transparentem Refresh.
 *
 * - Anlegen: `POST /me/contacts` mit `Prefer: IdType="ImmutableId"` — die ID
 *   bleibt beim Ordner-Move stabil (die ExternalReference bleibt gültig).
 * - Ändern: `PATCH /me/contacts/{id}`; 404 = Kontakt remote gelöscht →
 *   der Aufrufer legt neu an (idempotenter Push, keine Dubletten).
 */
class MsgraphContactsClient {
    private PluginApiClient $api;

    private string $base;

    public function __construct(private readonly MsgraphContactConnection $connection) {
        $this->base = MsgraphConfig::resolve()['api_base'];
        $this->api = app(PluginHttpFactory::class)->client(MsgraphPlugin::ID, $this->base);

        // Org der Verbindung explizit (Variante B: per-Org-App, queue-sicher).
        $orgId = (int) $connection->organization_id;
        $grant = MsgraphConfig::isConfigured($orgId) ? app(MsgraphContactsOAuth::class)->grantFor($orgId) : null;
        $this->api->setAuthentication(new OAuth2BearerAuthentication(new ConnectionTokenStore($this->connection), $grant));
    }

    /**
     * Legt den Kontakt an; Rückgabe = Graph-Kontakt-ID (immutable).
     *
     * @param  array<string, mixed>  $payload
     */
    public function createContact(array $payload): string {
        $response = $this->api->postJson($this->base . '/me/contacts', $payload, [
            'headers' => ['Prefer' => 'IdType="ImmutableId"'],
        ]);
        if (! $response->successful()) {
            throw new RuntimeException('Graph POST /me/contacts fehlgeschlagen (HTTP ' . $response->status() . ').');
        }

        $id = $response->json('id');
        if (! is_string($id) || $id === '') {
            throw new RuntimeException('Graph-Kontakt ohne ID angelegt.');
        }

        return $id;
    }

    /**
     * Aktualisiert den Kontakt; false = 404 (remote gelöscht → neu anlegen).
     *
     * @param  array<string, mixed>  $payload
     */
    public function updateContact(string $contactId, array $payload): bool {
        $response = $this->api->requestResponse('patch', $this->base . '/me/contacts/' . rawurlencode($contactId), [
            'json' => $payload,
            'headers' => ['Prefer' => 'IdType="ImmutableId"'],
        ]);
        if ($response->status() === 404) {
            return false;
        }
        if (! $response->successful()) {
            throw new RuntimeException('Graph PATCH /me/contacts fehlgeschlagen (HTTP ' . $response->status() . ').');
        }

        return true;
    }

    /**
     * Bestätigte Kontoidentität (`GET /me`) für das Admin-Panel.
     *
     * @return array{id: string, label: string}
     */
    public function account(): array {
        $response = $this->api->getResponse($this->base . '/me');
        if (! $response->successful()) {
            throw new RuntimeException('Graph /me fehlgeschlagen (HTTP ' . $response->status() . ').');
        }

        /** @var array{id?: string, displayName?: string, mail?: string, userPrincipalName?: string} $data */
        $data = (array) $response->json();

        return [
            'id' => (string) ($data['id'] ?? ''),
            'label' => trim((string) ($data['displayName'] ?? '') . ' <' . (string) ($data['mail'] ?? $data['userPrincipalName'] ?? '') . '>'),
        ];
    }

    /** Liveness/Auth-Check: Kontaktliste erreichbar. */
    public function ping(): bool {
        try {
            return $this->api->getResponse($this->base . '/me/contacts', ['$top' => '1', '$select' => 'id'])->successful();
        } catch (Throwable) {
            return false;
        }
    }
}
