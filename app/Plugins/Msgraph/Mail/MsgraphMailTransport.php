<?php
/*
 * Created on   : Wed Aug 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphMailTransport.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Msgraph\Mail;

use App\Models\MsgraphMailConnection;
use App\Plugins\Msgraph\Api\MsgraphMailClient;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\{Address, Email, MessageConverter};
use Symfony\Component\Mime\Part\DataPart;
use Throwable;

/**
 * Symfony-Mailer-Transport „msgraph" (Feature 102): versendet über
 * `POST /me/sendMail` (delegated `Mail.Send`) statt SMTP — Modern Auth,
 * Exchange-Online-Basic-Auth-Abschaltung umgangen.
 *
 * Verbindungsauflösung (mandantenfähig, queue-sicher):
 *  1. Header {@see self::HEADER_ORGANIZATION} (stampt der
 *     {@see StampOrganizationMailHeader}-Listener aus den Mailable-Daten) —
 *     wird vor dem Versand ENTFERNT und verlässt das Haus nie.
 *  2. Fallback: genau EINE aktive Verbindung instanzweit → diese.
 *  3. Sonst {@see TransportException} — bei einem failover-Mailer
 *     (`msgraph,smtp,log`) greift dann der nächste Transport.
 *
 * Bewusste Grenzen: Anhänge nur inline (≤ 3 MiB je Anhang — Rechnungs-PDFs
 * liegen weit darunter; Upload-Sessions für Riesen-Anhänge sind bewusst nicht
 * Teil des Piloten). HTML gewinnt gegen Text (Graph-Body kennt nur einen
 * contentType). Nur `X-*`-Header werden als internetMessageHeaders
 * durchgereicht (Graph-Restriktion) — der Zustellnachweis-Header
 * `X-WorkDiary-Dispatch` bleibt damit erhalten (M26).
 */
class MsgraphMailTransport extends AbstractTransport {
    /** Interner Routing-Header (Org-ID); wird vor dem Versand entfernt. */
    public const HEADER_ORGANIZATION = 'X-WorkDiary-Organization';

    /** Graph-Inline-Anhangsgrenze (darüber verlangt Graph eine Upload-Session). */
    public const INLINE_ATTACHMENT_LIMIT = 3 * 1024 * 1024;

    public function __toString(): string {
        return 'msgraph://graph.microsoft.com';
    }

    /** Ist der msgraph-Transport Teil der Default-Mailer-Kette (direkt oder failover/roundrobin)? */
    public static function inDefaultMailerChain(): bool {
        $default = (string) config('mail.default');
        if ($default === 'msgraph') {
            return true;
        }

        return in_array('msgraph', (array) config('mail.mailers.' . $default . '.mailers', []), true);
    }

    protected function doSend(SentMessage $message): void {
        $original = $message->getOriginalMessage();
        $email = $original instanceof Email ? $original : MessageConverter::toEmail($original); // @phpstan-ignore argument.type

        $connection = $this->resolveConnection($email);

        // Routing-Header ist rein intern — nie an den Empfänger.
        $email->getHeaders()->remove(self::HEADER_ORGANIZATION);

        try {
            $client = new MsgraphMailClient($connection);
            $payload = $this->payloadFrom($email, $connection);
            $large = $this->extractLargeAttachments($email);

            if ($large === []) {
                $client->sendMail($payload, $connection->save_to_sent_items);
            } else {
                // Große Anhänge (> 3 MiB): Draft + Upload-Session + Send
                // (braucht Mail.ReadWrite; saveToSentItems ist beim
                // Draft-Weg immer aktiv — Graph legt den Draft im Postfach ab).
                $draftId = $client->createDraft($payload);
                foreach ($large as $attachment) {
                    $client->uploadAttachment($draftId, $attachment['name'], $attachment['contentType'], $attachment['bytes']);
                }
                $client->sendDraft($draftId);
            }
        } catch (Throwable $e) {
            // Health-Zähler (Auto-Disable, MVP-178); nur Fehlerklasse, nie Payload.
            $connection->recordConnectionFailure(class_basename($e));

            throw new TransportException('Microsoft-Graph-Versand fehlgeschlagen (' . class_basename($e) . ').', 0, $e);
        }

        $connection->recordConnectionSuccess();
        $connection->markSent();
    }

    /** Verbindung auflösen: Org-Header → Org-Verbindung; sonst einzige aktive. */
    private function resolveConnection(Email $email): MsgraphMailConnection {
        $header = $email->getHeaders()->get(self::HEADER_ORGANIZATION);
        $orgId = $header !== null ? (int) trim($header->getBodyAsString()) : 0;

        // Queue-/Konsolen-Kontext hat keinen Org-Scope — explizit ungescopet.
        $query = MsgraphMailConnection::query()->withoutGlobalScopes();

        if ($orgId > 0) {
            $connection = (clone $query)->where('organization_id', $orgId)->first();
            if (! $connection instanceof MsgraphMailConnection || ! $connection->isActive()) {
                throw new TransportException('Keine aktive Microsoft-Graph-Mail-Verbindung für Organisation ' . $orgId . '.');
            }

            return $connection;
        }

        // **Kein Rückfall auf „die eine aktive Verbindung"** (Sicherheitsscan
        // 2026-08-23, S-28). Genau eine angebundene Organisation zu haben
        // heißt nicht, dass ihr Postfach die Systemmails aller anderen
        // versenden darf — Absenderidentität, und bei `save_to_sent_items`
        // landet eine Kopie mit OTP-Code oder Reset-Link in deren
        // Gesendet-Ordner.
        //
        // Zulässig bleibt der gebundene Organisationskontext: wer im Request
        // einer Organisation sitzt und ausdrücklich über Graph versendet,
        // meint deren Postfach.
        $contextOrg = app()->bound('currentOrganization') ? app('currentOrganization') : null;

        if ($contextOrg instanceof \App\Models\Organization) {
            $connection = (clone $query)->where('organization_id', $contextOrg->id)->first();

            if ($connection instanceof MsgraphMailConnection && $connection->isActive()) {
                return $connection;
            }
        }

        // Sonst: nichts raten. Die failover-Kette (SMTP) übernimmt.
        throw new TransportException(
            'Die Mail trägt keine Organisation — ohne sie ist keine Microsoft-Graph-Verbindung zuordenbar.',
        );
    }

    /**
     * Symfony-Email → Graph-`message`-Struktur.
     *
     * @return array<string, mixed>
     */
    private function payloadFrom(Email $email, MsgraphMailConnection $connection): array {
        $html = $email->getHtmlBody();
        $text = $email->getTextBody();
        $body = is_resource($html) ? stream_get_contents($html) : $html;
        if (! is_string($body) || $body === '') {
            $body = is_resource($text) ? (string) stream_get_contents($text) : (string) $text;
        }

        $message = [
            'subject' => (string) $email->getSubject(),
            'body' => [
                'contentType' => is_string($html) || is_resource($html) ? 'HTML' : 'Text',
                'content' => $body,
            ],
            'toRecipients' => $this->recipients($email->getTo()),
        ];

        if ($email->getCc() !== []) {
            $message['ccRecipients'] = $this->recipients($email->getCc());
        }
        if ($email->getBcc() !== []) {
            $message['bccRecipients'] = $this->recipients($email->getBcc());
        }
        if ($email->getReplyTo() !== []) {
            $message['replyTo'] = $this->recipients($email->getReplyTo());
        }

        // Shared-Mailbox-/Send-As-Absender (Exchange-Recht „Send As" nötig;
        // ohne from_address sendet Graph als das verbundene Konto).
        $from = trim((string) $connection->from_address);
        if ($from !== '') {
            $message['from'] = ['emailAddress' => ['address' => $from]];
        }

        // Kleine Anhänge inline; große (> 3 MiB) laufen über den
        // Draft-/Upload-Session-Weg ({@see extractLargeAttachments()}).
        $attachments = [];
        foreach ($email->getAttachments() as $part) {
            if (strlen($part->getBody()) > self::INLINE_ATTACHMENT_LIMIT) {
                continue;
            }
            $attachments[] = $this->attachment($part);
        }
        if ($attachments !== []) {
            $message['attachments'] = $attachments;
        }

        $custom = [];
        foreach ($email->getHeaders()->all() as $headerRow) {
            $name = $headerRow->getName();
            // Graph erlaubt nur X-*-Custom-Header; alles andere setzt Graph selbst.
            if (stripos($name, 'x-') === 0 && strcasecmp($name, self::HEADER_ORGANIZATION) !== 0) {
                $custom[] = ['name' => $name, 'value' => $headerRow->getBodyAsString()];
            }
        }
        if ($custom !== []) {
            $message['internetMessageHeaders'] = $custom;
        }

        return $message;
    }

    /**
     * @param  array<int, Address>  $addresses
     * @return list<array{emailAddress: array<string, string>}>
     */
    private function recipients(array $addresses): array {
        $recipients = [];
        foreach ($addresses as $address) {
            $entry = ['address' => $address->getAddress()];
            if ($address->getName() !== '') {
                $entry['name'] = $address->getName();
            }
            $recipients[] = ['emailAddress' => $entry];
        }

        return $recipients;
    }

    /** @return array<string, string> */
    private function attachment(DataPart $part): array {
        $filename = $part->getFilename();

        return [
            '@odata.type' => '#microsoft.graph.fileAttachment',
            'name' => $filename !== null && $filename !== '' ? $filename : 'anhang',
            'contentType' => $part->getMediaType() . '/' . $part->getMediaSubtype(),
            'contentBytes' => base64_encode($part->getBody()),
        ];
    }

    /**
     * Anhänge über der Inline-Grenze (Graph verlangt für 3–150 MB eine
     * Upload-Session am Draft).
     *
     * @return list<array{name: string, contentType: string, bytes: string}>
     */
    private function extractLargeAttachments(Email $email): array {
        $large = [];
        foreach ($email->getAttachments() as $part) {
            $bytes = $part->getBody();
            if (strlen($bytes) <= self::INLINE_ATTACHMENT_LIMIT) {
                continue;
            }
            $filename = $part->getFilename();
            $large[] = [
                'name' => $filename !== null && $filename !== '' ? $filename : 'anhang',
                'contentType' => $part->getMediaType() . '/' . $part->getMediaSubtype(),
                'bytes' => $bytes,
            ];
        }

        return $large;
    }
}
