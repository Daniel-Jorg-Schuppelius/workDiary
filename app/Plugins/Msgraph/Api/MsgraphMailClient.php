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
use App\Plugins\Msgraph\Models\MsgraphMailConnection;
use App\Plugins\Msgraph\{MsgraphConfig, MsgraphPlugin};
use App\Plugins\Support\{ConnectionTokenStore, PluginApiClient, PluginHttpFactory};
use App\Services\CloudIntake\StaleCheckpointException;
use DateTimeInterface;
use Illuminate\Support\Carbon;
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

    // ── Mail-EINGANG (Feature 102; Abruf nach Stand MVP-1139, Ordner MVP-1140,
    //    freigegebene Postfächer MVP-1141 — braucht Mail.ReadWrite bzw. .Shared) ──

    /** Feste Graph-Ordnernamen, die als erstes Pfadsegment direkt als ID gelten. */
    private const WELL_KNOWN_FOLDERS = ['inbox', 'archive', 'deleteditems', 'drafts', 'junkemail', 'sentitems', 'msgfolderroot'];

    private const MESSAGE_FIELDS = 'id,internetMessageId,subject,from,receivedDateTime,body,hasAttachments,internetMessageHeaders';

    /**
     * Eine Seite der Delta-Abfrage eines Ordners (nur neu hinzugekommene
     * Nachrichten). Ohne Link beginnt ein Durchgang, gefiltert ab `$since`;
     * `@removed`-Einträge (weggeschoben, gelöscht) fallen heraus.
     *
     * @return array{ids: list<string>, next: ?string, delta: ?string}
     *
     * @throws StaleCheckpointException wenn Graph einen Neuabgleich verlangt
     */
    public function messagesDelta(?string $mailbox, string $folderId, ?string $link, ?DateTimeInterface $since, int $pageSize): array {
        $options = ['headers' => ['Prefer' => 'odata.maxpagesize=' . max(1, $pageSize)]];
        if ($link !== null && $link !== '') {
            $response = $this->api->getFollowUp($link, $options);
        } else {
            $query = ['changeType' => 'created', '$select' => 'id,receivedDateTime'];
            if ($since !== null) {
                $query['$filter'] = 'receivedDateTime ge ' . Carbon::instance($since)->utc()->format('Y-m-d\TH:i:s\Z');
            }
            $response = $this->api->getResponse($this->mailboxBase($mailbox) . '/mailFolders/' . rawurlencode($folderId) . '/messages/delta', $query, $options);
        }

        $code = mb_strtolower((string) $response->json('error.code', ''));
        if ($response->status() === 410 || ($response->clientError() && (str_contains($code, 'syncstate') || str_contains($code, 'resync')))) {
            throw new StaleCheckpointException('Graph-Mail-Delta abgelaufen (HTTP ' . $response->status() . ').');
        }
        if (! $response->successful()) {
            throw new RuntimeException('Graph messages/delta fehlgeschlagen (HTTP ' . $response->status() . ').');
        }

        /** @var array{value?: list<array<string, mixed>>, '@odata.nextLink'?: string, '@odata.deltaLink'?: string} $data */
        $data = (array) $response->json();
        $ids = [];
        foreach ((array) ($data['value'] ?? []) as $entry) {
            if (! isset($entry['@removed']) && is_string($entry['id'] ?? null) && $entry['id'] !== '') {
                $ids[] = $entry['id'];
            }
        }

        return [
            'ids' => $ids,
            'next' => isset($data['@odata.nextLink']) ? (string) $data['@odata.nextLink'] : null,
            'delta' => isset($data['@odata.deltaLink']) ? (string) $data['@odata.deltaLink'] : null,
        ];
    }

    /**
     * Eine Nachricht mit Kopfzeilen (Threading, Loop-Schutz); null, wenn sie
     * zwischen Delta und Abruf verschwunden ist.
     *
     * @return array<string, mixed>|null
     */
    public function message(?string $mailbox, string $messageId): ?array {
        $response = $this->api->getResponse($this->mailboxBase($mailbox) . '/messages/' . rawurlencode($messageId), ['$select' => self::MESSAGE_FIELDS]);
        if ($response->status() === 404) {
            return null;
        }
        if (! $response->successful()) {
            throw new RuntimeException('Graph message-Abruf fehlgeschlagen (HTTP ' . $response->status() . ').');
        }

        return (array) $response->json();
    }

    /**
     * Datei-Anhänge einer Nachricht (base64). Eine angehängte Mail
     * (`itemAttachment`, „Als Anlage weiterleiten“) liefert ihre Dateianhänge
     * mit, eine Ebene tief (MVP-1142); Verweise auf Cloud-Dateien bleiben
     * außen vor. Fehlt `contentBytes`, wird der Rohinhalt nachgeladen.
     *
     * @return list<array{name: string, contentType: string, contentBytes: string, isInline: bool}>
     */
    public function messageAttachments(?string $mailbox, string $messageId): array {
        $base = $this->mailboxBase($mailbox) . '/messages/' . rawurlencode($messageId) . '/attachments';
        $response = $this->api->getResponse($base);
        if (! $response->successful()) {
            return [];
        }

        $out = [];
        foreach ((array) $response->json('value', []) as $attachment) {
            if (! is_array($attachment)) {
                continue;
            }
            $type = (string) ($attachment['@odata.type'] ?? '');
            $id = (string) ($attachment['id'] ?? '');
            if ($type === '#microsoft.graph.fileAttachment') {
                if ((string) ($attachment['contentBytes'] ?? '') === '' && $id !== '') {
                    $raw = $this->api->getResponse($base . '/' . rawurlencode($id) . '/$value');
                    $attachment['contentBytes'] = $raw->successful() ? base64_encode($raw->body()) : '';
                }
                $out[] = self::fileAttachment($attachment);
            } elseif ($type === '#microsoft.graph.itemAttachment' && $id !== '') {
                $item = $this->api->getResponse($base . '/' . rawurlencode($id), ['$expand' => 'microsoft.graph.itemattachment/item']);
                foreach ($item->successful() ? (array) $item->json('item.attachments', []) : [] as $nested) {
                    if (is_array($nested) && ($nested['@odata.type'] ?? '') === '#microsoft.graph.fileAttachment') {
                        $out[] = self::fileAttachment($nested);
                    }
                }
            }
        }

        return $out;
    }

    /** Nachricht als gelesen markieren (Verarbeitungs-Kennzeichnung, nie löschen). */
    public function markRead(?string $mailbox, string $messageId): void {
        $this->api->requestResponse('patch', $this->mailboxBase($mailbox) . '/messages/' . rawurlencode($messageId), [
            'json' => ['isRead' => true],
        ]);
    }

    public function moveToFolder(?string $mailbox, string $messageId, string $folderId): void {
        $this->api->postJson($this->mailboxBase($mailbox) . '/messages/' . rawurlencode($messageId) . '/move', [
            'destinationId' => $folderId,
        ]);
    }

    /**
     * Ordner-ID zu einem Pfad mit `/` (MVP-1140). Das erste Segment darf ein
     * fester Name wie `inbox` sein (`INBOX` aus der IMAP-Konvention ebenso),
     * sonst zählt der Anzeigename ohne Rücksicht auf die Schreibweise.
     */
    public function folderId(?string $mailbox, string $path): ?string {
        $segments = array_values(array_filter(array_map('trim', explode('/', $path)), static fn (string $s): bool => $s !== ''));
        if ($segments === []) {
            return 'inbox';
        }

        $parent = null;
        if (in_array(mb_strtolower($segments[0]), self::WELL_KNOWN_FOLDERS, true)) {
            $parent = mb_strtolower(array_shift($segments));
        }
        foreach ($segments as $segment) {
            $match = null;
            foreach ($this->childFolders($mailbox, $parent) as $folder) {
                if (mb_strtolower($folder['name']) === mb_strtolower($segment)) {
                    $match = $folder;

                    break;
                }
            }
            if ($match === null) {
                return null;
            }
            $parent = $match['id'];
        }

        return $parent;
    }

    /**
     * Ordnerpfade für die Auswahl im Dialog, Tiefe und Anzahl begrenzt.
     *
     * @return list<string>
     */
    public function folderPaths(?string $mailbox, int $maxDepth = 4, int $maxFolders = 500): array {
        $paths = [];
        $walk = function (?string $parentId, string $prefix, int $depth) use (&$walk, &$paths, $mailbox, $maxDepth, $maxFolders): void {
            foreach ($this->childFolders($mailbox, $parentId) as $folder) {
                if (count($paths) >= $maxFolders) {
                    return;
                }
                $path = $prefix === '' ? $folder['name'] : $prefix . '/' . $folder['name'];
                $paths[] = $path;
                if ($folder['children'] > 0 && $depth < $maxDepth) {
                    $walk($folder['id'], $path, $depth + 1);
                }
            }
        };
        $walk(null, '', 1);

        return $paths;
    }

    /**
     * Unterordner (oberste Ebene bei `null`), Folgeseiten eingeschlossen.
     *
     * @return list<array{id: string, name: string, children: int}>
     */
    private function childFolders(?string $mailbox, ?string $parentId): array {
        $url = $this->mailboxBase($mailbox) . '/mailFolders' . ($parentId !== null ? '/' . rawurlencode($parentId) . '/childFolders' : '');
        $response = $this->api->getResponse($url, ['$top' => '250', '$select' => 'id,displayName,childFolderCount']);
        $folders = [];
        while (true) {
            if (! $response->successful()) {
                throw new RuntimeException('Graph mailFolders fehlgeschlagen (HTTP ' . $response->status() . ').');
            }
            foreach ((array) $response->json('value', []) as $folder) {
                if (is_array($folder) && (string) ($folder['id'] ?? '') !== '') {
                    $folders[] = ['id' => (string) $folder['id'], 'name' => (string) ($folder['displayName'] ?? ''), 'children' => (int) ($folder['childFolderCount'] ?? 0)];
                }
            }
            $next = $response->json('@odata.nextLink');
            if (! is_string($next) || $next === '') {
                return $folders;
            }
            $response = $this->api->getFollowUp($next);
        }
    }

    /** Eigenes Postfach des verbundenen Kontos oder ein freigegebenes per Adresse. */
    private function mailboxBase(?string $mailbox): string {
        $address = trim((string) $mailbox);

        return $this->base . ($address === '' ? '/me' : '/users/' . rawurlencode($address));
    }

    /**
     * @param  array<string, mixed>  $attachment
     * @return array{name: string, contentType: string, contentBytes: string, isInline: bool}
     */
    private static function fileAttachment(array $attachment): array {
        return [
            'name' => (string) ($attachment['name'] ?? 'anhang'),
            'contentType' => (string) ($attachment['contentType'] ?? 'application/octet-stream'),
            'contentBytes' => (string) ($attachment['contentBytes'] ?? ''),
            'isInline' => (bool) ($attachment['isInline'] ?? false),
        ];
    }
}
