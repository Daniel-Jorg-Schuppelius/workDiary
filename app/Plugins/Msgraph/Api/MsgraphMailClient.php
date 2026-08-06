<?php
/*
 * Created on   : Wed Aug 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphMailClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Msgraph\Api;

use APIToolkit\API\Authentication\OAuth2\OAuth2BearerAuthentication;
use App\Models\MsgraphMailConnection;
use App\Plugins\Msgraph\{MsgraphConfig, MsgraphPlugin};
use App\Plugins\Support\{ConnectionTokenStore, PluginApiClient, PluginHttpFactory};
use RuntimeException;
use Throwable;

/**
 * Microsoft-Graph-Mail-Gateway (Feature 102) auf dem `php-api-toolkit`-
 * Fundament: OAuth2-Bearer über den org-gebundenen {@see ConnectionTokenStore}
 * inkl. transparentem Refresh (401 ⇒ Refresh ⇒ genau ein Retry).
 *
 * `POST /me/sendMail` antwortet mit `202 Accepted` — Graph hat die Nachricht
 * ANGENOMMEN, die Zustellung selbst ist asynchron (wie SMTP-Accept). Die
 * Payload-Konvertierung (Symfony-Email → Graph-JSON) liegt im
 * {@see \App\Plugins\Msgraph\Mail\MsgraphMailTransport}; dieser Client bleibt
 * reiner HTTP-Zugriff.
 */
class MsgraphMailClient implements GraphSubscriptionClient {
    use Concerns\ManagesGraphSubscriptions;

    /** Chunk-Größe der Anhangs-Upload-Session: Vielfaches von 320 KiB (Graph-Vorgabe). */
    public const UPLOAD_CHUNK_SIZE = 10 * 320 * 1024;

    private PluginApiClient $api;

    /** Auth-loser Client für Chunk-PUTs an die selbst-autorisierende Session-URL. */
    private PluginApiClient $uploadApi;

    private string $base;

    public function __construct(private readonly MsgraphMailConnection $connection) {
        $this->base = MsgraphConfig::resolve()['api_base'];
        $this->api = app(PluginHttpFactory::class)->client(MsgraphPlugin::ID, $this->base);
        $this->uploadApi = app(PluginHttpFactory::class)->client(MsgraphPlugin::ID, $this->base);

        // Grant nur bei vorhandener Konfiguration — ohne ihn bleibt das
        // Bearer-Token nutzbar, nur ohne Refresh-Möglichkeit. Org der
        // Verbindung explizit (Variante B: per-Org-App, queue-sicher —
        // der Mail-Transport läuft im Worker ohne Org-Kontext).
        $orgId = (int) $connection->organization_id;
        $grant = MsgraphConfig::isConfigured($orgId) ? app(MsgraphMailOAuth::class)->grantFor($orgId) : null;
        $this->api->setAuthentication(new OAuth2BearerAuthentication(new ConnectionTokenStore($this->connection), $grant));
    }

    /**
     * Versendet eine Graph-`message`-Struktur. Wirft bei jedem Nicht-2xx —
     * die Fehlerbehandlung (Health, TransportException) liegt beim Transport.
     *
     * @param  array<string, mixed>  $message
     */
    public function sendMail(array $message, bool $saveToSentItems = true): void {
        $response = $this->api->postJson($this->base . '/me/sendMail', [
            'message' => $message,
            'saveToSentItems' => $saveToSentItems,
        ]);

        if (! $response->successful()) {
            // Nur Statuscode — nie Payload/Empfänger/Token in Fehlermeldungen.
            throw new RuntimeException('Graph sendMail fehlgeschlagen (HTTP ' . $response->status() . ').');
        }
    }

    // ── Große Anhänge (> 3 MiB): Draft + Upload-Session (braucht Mail.ReadWrite) ──

    /**
     * Legt einen Nachrichten-Entwurf an (für den Upload-Session-Versandweg).
     *
     * @param  array<string, mixed>  $message
     */
    public function createDraft(array $message): string {
        $response = $this->api->postJson($this->base . '/me/messages', $message);
        if (! $response->successful()) {
            throw new RuntimeException('Graph POST /me/messages (Draft) fehlgeschlagen (HTTP ' . $response->status() . ') — großer Anhang braucht den Scope Mail.ReadWrite.');
        }

        $id = $response->json('id');
        if (! is_string($id) || $id === '') {
            throw new RuntimeException('Graph-Draft ohne ID angelegt.');
        }

        return $id;
    }

    /**
     * Lädt einen großen Anhang über eine Upload-Session an den Draft
     * (Chunk-PUTs in 320-KiB-Vielfachen, laut Graph-Doku OHNE
     * Authorization-Header — die Session-URL ist selbst-autorisierend).
     */
    public function uploadAttachment(string $messageId, string $name, string $contentType, string $bytes): void {
        $session = $this->api->postJson($this->base . '/me/messages/' . rawurlencode($messageId) . '/attachments/createUploadSession', [
            'AttachmentItem' => [
                'attachmentType' => 'file',
                'name' => $name,
                'contentType' => $contentType,
                'size' => strlen($bytes),
            ],
        ]);
        $uploadUrl = (string) $session->json('uploadUrl', '');
        if (! $session->successful() || $uploadUrl === '') {
            throw new RuntimeException('Graph createUploadSession (Anhang) fehlgeschlagen (HTTP ' . $session->status() . ').');
        }

        $total = strlen($bytes);
        for ($offset = 0; $offset < $total; $offset += self::UPLOAD_CHUNK_SIZE) {
            $chunk = substr($bytes, $offset, self::UPLOAD_CHUNK_SIZE);
            $last = $offset + strlen($chunk) - 1;

            $response = $this->uploadApi->requestResponse('put', $uploadUrl, [
                'headers' => [
                    'Content-Length' => (string) strlen($chunk),
                    'Content-Range' => sprintf('bytes %d-%d/%d', $offset, $last, $total),
                ],
                'body' => $chunk,
            ]);
            // Zwischenchunks: 200/202; letzter Chunk: 201 mit Attachment.
            if (! $response->successful()) {
                throw new RuntimeException('Graph-Anhangs-Chunk fehlgeschlagen (HTTP ' . $response->status() . ').');
            }
        }
    }

    /** Versendet den Draft (`POST /me/messages/{id}/send`, 202). */
    public function sendDraft(string $messageId): void {
        $response = $this->api->postJson($this->base . '/me/messages/' . rawurlencode($messageId) . '/send');
        if (! $response->successful()) {
            throw new RuntimeException('Graph Draft-Send fehlgeschlagen (HTTP ' . $response->status() . ').');
        }
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

    /** Liveness/Auth-Check: Konto erreichbar. */
    public function ping(): bool {
        try {
            return $this->api->getResponse($this->base . '/me', ['$select' => 'id'])->successful();
        } catch (Throwable) {
            return false;
        }
    }

    // ── Mail-EINGANG (Feature 102, MS365-Plan B — braucht Mail.ReadWrite) ──

    /**
     * Ungelesene Nachrichten eines Ordners (Default: Inbox), älteste zuerst.
     *
     * @return list<array<string, mixed>>
     */
    public function listUnreadMessages(string $folder = 'inbox', int $limit = 100): array {
        $response = $this->api->getResponse(
            $this->base . '/me/mailFolders/' . rawurlencode($folder !== '' ? $folder : 'inbox') . '/messages',
            [
                '$filter' => 'isRead eq false',
                '$orderby' => 'receivedDateTime asc',
                '$top' => (string) max(1, min(100, $limit)),
                '$select' => 'id,internetMessageId,subject,from,receivedDateTime,body,hasAttachments,internetMessageHeaders',
            ],
        );
        if (! $response->successful()) {
            throw new RuntimeException('Graph messages-Abruf fehlgeschlagen (HTTP ' . $response->status() . ').');
        }

        /** @var array{value?: list<array<string, mixed>>} $data */
        $data = (array) $response->json();

        return $data['value'] ?? [];
    }

    /**
     * Datei-Anhänge einer Nachricht (fileAttachment, base64 → roh).
     *
     * @return list<array{name: string, contentType: string, contentBytes: string}>
     */
    public function messageAttachments(string $messageId): array {
        $response = $this->api->getResponse($this->base . '/me/messages/' . rawurlencode($messageId) . '/attachments');
        if (! $response->successful()) {
            return [];
        }

        $out = [];
        /** @var array{value?: list<array{'@odata.type'?: string, name?: string, contentType?: string, contentBytes?: string}>} $data */
        $data = (array) $response->json();
        foreach ((array) ($data['value'] ?? []) as $attachment) {
            if (($attachment['@odata.type'] ?? '') !== '#microsoft.graph.fileAttachment') {
                continue; // item-/reference-Attachments bewusst außen vor
            }
            $out[] = [
                'name' => (string) ($attachment['name'] ?? 'anhang'),
                'contentType' => (string) ($attachment['contentType'] ?? 'application/octet-stream'),
                'contentBytes' => (string) ($attachment['contentBytes'] ?? ''),
            ];
        }

        return $out;
    }

    /** Nachricht als gelesen markieren (Verarbeitungs-Kennzeichnung, nie löschen). */
    public function markRead(string $messageId): void {
        $this->api->requestResponse('patch', $this->base . '/me/messages/' . rawurlencode($messageId), [
            'json' => ['isRead' => true],
        ]);
    }

    /** Nachricht in einen Ordner verschieben (Ordnername → ID-Lookup). */
    public function moveToFolder(string $messageId, string $folderName): void {
        $folderId = $this->folderIdByName($folderName);
        if ($folderId === null) {
            return; // Zielordner existiert nicht — Nachricht bleibt (best effort)
        }
        $this->api->postJson($this->base . '/me/messages/' . rawurlencode($messageId) . '/move', [
            'destinationId' => $folderId,
        ]);
    }

    /** Ordner-ID zu einem Anzeigenamen (Top-Level). */
    private function folderIdByName(string $folderName): ?string {
        $response = $this->api->getResponse($this->base . '/me/mailFolders', [
            '$filter' => "displayName eq '" . str_replace("'", "''", $folderName) . "'",
            '$select' => 'id',
        ]);
        if (! $response->successful()) {
            return null;
        }
        $id = $response->json('value.0.id');

        return is_string($id) && $id !== '' ? $id : null;
    }
}
