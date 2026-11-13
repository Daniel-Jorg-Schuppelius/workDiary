<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MailFileReader.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Attachments\Mail;

use App\Enums\Attachments\{MailFileFailure, MailFileFormat};
use App\Exceptions\MailFileUnreadableException;
use App\Models\Attachments\Attachment;
use App\Services\Attachments\FileAttacher;
use App\Services\Attachments\Mail\Dto\{MailFileMessage, MailFilePart};
use CommonToolkit\Entities\Mail\{Attachment as ToolkitAttachment, Message as ToolkitMessage, Recipient};
use CommonToolkit\Exceptions\Parsers\DocumentLimitExceededException;
use CommonToolkit\Helper\Data\{DataUrlHelper, StringHelper};
use CommonToolkit\Helper\FileSystem\File;
use CommonToolkit\Parsers\{CompoundFileReader, OutlookMessageParser};
use Illuminate\Support\Facades\Storage;
use Throwable;
use Webklex\PHPIMAP\{Address as ImapAddress, Attribute as ImapAttribute, Config as ImapConfig, Message as ImapMessage};

/**
 * Mail-Leser für abgelegte `.eml`- und `.msg`-Dateien (Feature 204,
 * MVP-1652) mit einem Ergebnis für beide Formate ({@see MailFileMessage}).
 *
 * - `.msg` liest der `OutlookMessageParser` des common-toolkits direkt hier
 *   (ohne Dateinamen: die Meldungen des Toolkits landen im Fehlerprotokoll).
 * - `.eml` liest `webklex/php-imap` mit iconv-Dekodierung der Kopfzeilen.
 *
 * Zwei Stufen:
 * - {@see precheck()} — billig und linear, beim Hochladen: Typ am Inhalt wurde
 *   schon geprüft; hier Größe, MIME-Aufbau ({@see assertMimeStructure()}) bzw.
 *   Compound-File-Kennung. Gelesen wird beim Hochladen nichts.
 * - {@see read()} — vollständig, nur im Hintergrund
 *   ({@see \App\Jobs\Attachments\ReadMailAttachmentJob}) und im Dokument-Textjob.
 *
 * webklex hat keine Grenzen und mehrere teure Stellen (Sicherheitsprüfung
 * 2026-11-12): Kopfzeilen eines Teils entfernt es Zeile für Zeile per
 * `substr()` (Aufwand Zeilen × Teilgröße), Adresslisten zerlegt es mit einem
 * Muster, das je Komma den Rest durchsucht, und eine leere Boundary zerlegt
 * einen Strich-Teppich in Millionen Teile. {@see assertMimeStructure()} misst
 * genau diese Größen vorab und linear und läuft vor jedem webklex-Aufruf.
 */
final class MailFileReader {
    /** Kopfzeilen je MIME-Teil — echte Teile haben unter 20. */
    private const int MAX_PART_HEADER_LINES = 200;

    /** Verschachtelung von multipart-Teilen innerhalb einer Mail. */
    private const int MAX_MIME_NESTING = 8;

    /** multipart-Behälter je Mail (begrenzt das Suchen nach Grenzen). */
    private const int MAX_CONTAINERS = 32;

    /** Kopfbereich je Mail bzw. MIME-Teil in Bytes. */
    private const int MAX_HEADER_BYTES = 128 * 1024;

    /** Kopfbereiche aller Teile und eingebetteten Mails zusammen. */
    private const int MAX_TOTAL_HEADER_BYTES = 256 * 1024;

    /** Adressen in Adressköpfen (From, To, Cc, Bcc, Reply-To, Sender …) über alle Teile und Mails. */
    private const int MAX_ADDRESSES = 1000;

    /** Kopieraufwand der Kopfzeilen-Schleife von webklex (Zeilen × Restlänge) über alle Teile. */
    private const int MAX_COPY_BYTES = 1024 * 1024 * 1024;

    /** RFC 2046: eine Boundary hat 1 bis 70 Zeichen. */
    private const int MAX_BOUNDARY_LENGTH = 70;

    /** Längster gelesener Text je Mail (Zeichen); der Rest ist für Ansicht und Suche entbehrlich. */
    public const int MAX_TEXT_CHARS = 500_000;

    /** Größter HTML-Teil für die abgeschottete Originaldarstellung. */
    private const int MAX_FRAME_HTML_BYTES = 2 * 1024 * 1024;

    /** Tags im HTML-Teil: der HTML5-Parser wird bei tiefer Verschachtelung quadratisch. */
    private const int MAX_FRAME_HTML_TAGS = 50_000;

    /** Größtes eingebettetes Bild (cid:), das als data:-URI in die HTML-Ansicht geht. */
    private const int MAX_INLINE_IMAGE_BYTES = 2 * 1024 * 1024;

    /** Alle eingebetteten Bilder einer HTML-Ansicht zusammen. */
    private const int MAX_INLINE_IMAGES_TOTAL_BYTES = 10 * 1024 * 1024;

    /** Elemente, die in der Originaldarstellung nichts zu suchen haben (Weiterleitung, Nachladen, Skript). */
    private const string FRAME_REMOVED_ELEMENTS = 'meta, link, base, script, noscript, iframe, frame, frameset, object, embed, applet';

    /** Adressköpfe, die webklex als Adressliste zerlegt. */
    private const string ADDRESS_HEADER_PATTERN = '/^(?:from|to|cc|bcc|reply-to|sender|return-path|envelope-from|envelope-to|delivered-to)[ \t]*:([^\r\n]*)/im';

    /** Platzhalter für unlesbare Datumsangaben (webklex wirft sonst); Zeitstempel 0 wird zu „kein Datum“. */
    private const string FALLBACK_DATE = '@0';

    private int $attachmentCount = 0;

    private int $attachmentBytes = 0;

    /** @var array{header_bytes: int, addresses: int, copy: int, parts: int, containers: int} */
    private array $budget = ['header_bytes' => 0, 'addresses' => 0, 'copy' => 0, 'parts' => 0, 'containers' => 0];

    /**
     * Grenzen des Lesers: Dateigröße höchstens `mail_files.max_kb` und nie mehr
     * als die Grenze für Anhänge ({@see FileAttacher::maxKb()}); Anzahl und
     * Gesamtgröße der Anhänge über alle Ebenen; Tiefe eingebetteter Mails.
     *
     * @return array{bytes: int, attachments: int, attachment_bytes: int, depth: int}
     */
    public static function limits(): array {
        $bytes = max(1, min(FileAttacher::maxKb(), (int) config('mail_files.max_kb', 25600))) * 1024;

        return [
            'bytes' => $bytes,
            'attachments' => max(0, (int) config('mail_files.max_attachments', 100)),
            'attachment_bytes' => max(0, min($bytes, (int) config('mail_files.max_attachment_kb', 25600) * 1024)),
            'depth' => max(0, (int) config('mail_files.max_depth', 2)),
        ];
    }

    /**
     * Billige Prüfung beim Hochladen — linear, ohne Parser: Größe, beim `.eml`
     * der MIME-Aufbau und typische Mail-Kopfzeilen, beim `.msg` die
     * Compound-File-Kennung. Den Typ am Inhalt prüft der Aufrufer.
     *
     * @throws MailFileUnreadableException
     */
    public function precheck(string $bytes, MailFileFormat $format): void {
        if (strlen($bytes) > self::limits()['bytes']) {
            throw new MailFileUnreadableException(MailFileFailure::TooLarge);
        }
        if ($bytes === '') {
            throw new MailFileUnreadableException(MailFileFailure::Malformed);
        }
        $this->resetBudget();

        if ($format === MailFileFormat::Msg) {
            if (! CompoundFileReader::isCompoundFile($bytes)) {
                throw new MailFileUnreadableException(MailFileFailure::Malformed);
            }

            return;
        }

        $header = $this->assertMimeStructure($bytes, self::maxParts());
        if (preg_match('/^(?:from|date|subject|message-id|to|received)[ \t]*:/im', $header) !== 1) {
            throw new MailFileUnreadableException(MailFileFailure::Malformed);
        }
    }

    /**
     * Liest eine Mail-Datei vollständig — nur im Hintergrund aufrufen.
     *
     * @throws MailFileUnreadableException
     */
    public function read(string $bytes, MailFileFormat $format): MailFileMessage {
        $limits = self::limits();
        if (strlen($bytes) > $limits['bytes']) {
            throw new MailFileUnreadableException(MailFileFailure::TooLarge);
        }
        if ($bytes === '') {
            throw new MailFileUnreadableException(MailFileFailure::Malformed);
        }

        $this->attachmentCount = 0;
        $this->attachmentBytes = 0;
        $this->resetBudget();

        try {
            return match ($format) {
                MailFileFormat::Eml => $this->readEml($bytes, 0, $limits),
                // Ohne Dokumentnamen: das Toolkit protokolliert Fehler sonst mit dem Dateinamen.
                MailFileFormat::Msg => $this->fromToolkit(
                    OutlookMessageParser::fromString($bytes, $limits['bytes'], $limits['attachments'], $limits['attachment_bytes'], $limits['depth']),
                    MailFileFormat::Msg,
                ),
            };
        } catch (MailFileUnreadableException $e) {
            throw $e;
        } catch (DocumentLimitExceededException $e) {
            throw new MailFileUnreadableException(match ($e->getKind()) {
                DocumentLimitExceededException::KIND_ENTRIES => MailFileFailure::TooManyAttachments,
                DocumentLimitExceededException::KIND_DEPTH => MailFileFailure::TooDeep,
                default => MailFileFailure::AttachmentsTooLarge,
            }, $e);
        } catch (Throwable $e) {
            // Beschädigt, keine Mail oder ein Fehler im Parser: für die Person dasselbe.
            throw new MailFileUnreadableException(MailFileFailure::Malformed, $e);
        }
    }

    /**
     * Liest eine Datei im lokalen Dateisystem (Dokument-Textjob).
     *
     * @throws MailFileUnreadableException
     */
    public function readFile(string $path, MailFileFormat $format): MailFileMessage {
        if (! File::isFile($path)) {
            throw new MailFileUnreadableException(MailFileFailure::Missing);
        }
        if (File::size($path) > self::limits()['bytes']) {
            throw new MailFileUnreadableException(MailFileFailure::TooLarge);
        }

        return $this->read(File::read($path), $format);
    }

    /**
     * Liest einen gespeicherten Anhang vollständig (mit HTML und Anhangsinhalten).
     *
     * @throws MailFileUnreadableException Kein Mail-Anhang, Datei fehlt oder nicht lesbar
     */
    public function readAttachment(Attachment $attachment): MailFileMessage {
        $format = $attachment->mailFormat() ?? throw new MailFileUnreadableException(MailFileFailure::Malformed);
        $disk = Storage::disk((string) $attachment->disk);
        if (! $disk->exists((string) $attachment->path)) {
            throw new MailFileUnreadableException(MailFileFailure::Missing);
        }
        // Größe vor dem Laden prüfen: ein später gesenktes Limit soll nichts mehr in den Speicher holen.
        if ((int) $disk->size((string) $attachment->path) > self::limits()['bytes']) {
            throw new MailFileUnreadableException(MailFileFailure::TooLarge);
        }

        return $this->read((string) $disk->get((string) $attachment->path), $format);
    }

    /**
     * Suchtext einer Mail: Betreff, Absender, Empfänger, Text und die Namen der
     * Anhänge, eingebettete Mails eingeschlossen; höchstens {@see MAX_TEXT_CHARS}.
     */
    public function searchText(MailFileMessage $message): string {
        $lines = [
            $message->subject,
            MailFileMessage::addressLine($message->from),
            MailFileMessage::addressList([...$message->to, ...$message->cc]),
            $message->text,
        ];
        foreach ($message->attachments as $part) {
            $lines[] = $part->message !== null ? $this->searchText($part->message) : $part->name;
        }
        $text = trim(implode("\n", array_filter($lines, static fn (string $line): bool => trim($line) !== '')));

        return mb_substr($text, 0, self::MAX_TEXT_CHARS);
    }

    /**
     * Original-HTML für den abgeschotteten Rahmen, bereinigt über den
     * HTML5-Parser von PHP (Dom\HTMLDocument, wie ein Browser): `meta`
     * (Weiterleitung, auch mit `>` im Attribut oder Zeichenreferenz), `link`,
     * `base`, Skripte, `noscript`, eingebettete Rahmen und Objekte sowie alle
     * Kommentare fallen weg. Eingebettete Bilder (`cid:`) werden data:-URIs,
     * sofern sie als PNG/JPEG/GIF/WebP erkannt werden und klein genug sind.
     * Fremdbilder, Formulare und Skripte sperren zusätzlich CSP und `sandbox`.
     *
     * Zu großes oder zu verschachteltes HTML → null (keine Originaldarstellung).
     */
    public function frameHtml(MailFileMessage $message): ?string {
        $html = (string) $message->html;
        if ($html === '' || strlen($html) > self::MAX_FRAME_HTML_BYTES || substr_count($html, '<') > self::MAX_FRAME_HTML_TAGS) {
            return null;
        }

        try {
            $document = \Dom\HTMLDocument::createFromString($html, LIBXML_NOERROR, 'UTF-8');
            foreach ($document->querySelectorAll(self::FRAME_REMOVED_ELEMENTS) as $element) {
                $element->remove();
            }
            $comments = (new \Dom\XPath($document))->query('//comment()');
            foreach ($comments as $comment) {
                $comment->parentNode?->removeChild($comment);
            }
            $clean = $document->saveHtml();
        } catch (Throwable) {
            return null;
        }

        $images = [];
        $budget = self::MAX_INLINE_IMAGES_TOTAL_BYTES;
        foreach ($message->attachments as $part) {
            if ($part->contentId === null || $part->isMessage() || ! str_starts_with($part->mime, 'image/') || ! $part->isPreviewable()) {
                continue;
            }
            $size = strlen($part->content);
            if ($size === 0 || $size > self::MAX_INLINE_IMAGE_BYTES || $size > $budget) {
                continue;
            }
            $dataUrl = DataUrlHelper::encode($part->content, $part->mime);
            if ($dataUrl !== false) {
                $images[strtolower($part->contentId)] = $dataUrl;
                $budget -= $size;
            }
        }

        return (string) preg_replace_callback(
            '/\bcid:([^\s"\'<>()&]+)/i',
            static fn (array $m): string => $images[strtolower(rawurldecode($m[1]))] ?? $m[0],
            $clean,
        );
    }

    /**
     * HTML → Text über `StringHelper::htmlToText()` (wie der OneNote-Import),
     * nachdem `<script|style|head|title|template>`-Blöcke linear entfernt sind.
     */
    public static function htmlToText(string $html): string {
        $out = '';
        $offset = 0;
        $length = strlen($html);
        /** @var array<string, int|false> $closers nächstes Ende je Element ab der letzten Suche */
        $closers = [];
        while ($offset < $length && preg_match('/<(script|style|head|title|template)\b/i', $html, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $start = (int) $match[0][1];
            $name = strtolower((string) $match[1][0]);
            // Leerzeichen statt des Blocks: sonst setzten sich Reste wie „<sty“ + „le>“ zu neuen Öffnern zusammen.
            $out .= substr($html, $offset, $start - $offset) . ' ';

            $from = $start + strlen((string) $match[0][0]);
            $closer = $closers[$name] ?? null;
            if ($closer === null || ($closer !== false && $closer < $from)) {
                // Einmal je Element suchen: ohne Gegenstück steht „false“ fest, und kein weiterer Öffner sucht erneut.
                $closer = stripos($html, '</' . $name, $from);
                $closers[$name] = $closer;
            }
            if ($closer === false) {
                // Öffner ohne Gegenstück: nur den Öffner verwerfen.
                $offset = $from;

                continue;
            }
            $end = strpos($html, '>', $closer);
            $offset = $end === false ? $length : $end + 1;
        }
        $out .= substr($html, $offset);

        return StringHelper::htmlToText($out);
    }

    /**
     * @param  array{bytes: int, attachments: int, attachment_bytes: int, depth: int}  $limits
     */
    private function readEml(string $bytes, int $depth, array $limits): MailFileMessage {
        // Vor jedem webklex-Aufruf, auch für eingebettete Mails; die Budgets gelten für die ganze Datei.
        $this->assertMimeStructure($bytes, self::maxParts());

        $message = ImapMessage::fromString($bytes, self::imapConfig());
        $html = $message->hasHTMLBody() ? mb_scrub($message->getHTMLBody(), 'UTF-8') : null;
        $text = mb_scrub($message->getTextBody(), 'UTF-8');

        $attachments = [];
        foreach ($message->getAttachments()->values() as $attachment) {
            $content = (string) $attachment->getContent();
            $this->countAttachment(strlen($content), $limits);

            $attributes = $attachment->getAttributes();
            $contentType = strtolower(trim((string) ($attachment->content_type ?? '')));
            $filename = (string) ($attachment->filename ?? '');
            if ($filename === (string) ($attachment->hash ?? '')) {
                // webklex setzt den Prüfwert als Namen, wenn die Kopfzeile keinen nennt.
                $filename = '';
            }
            $filename = $filename !== '' ? $filename : (string) ($attachment->name ?? '');

            if ($contentType === 'message/rfc822') {
                if ($depth + 1 > $limits['depth']) {
                    throw new MailFileUnreadableException(MailFileFailure::TooDeep);
                }
                $nested = $this->readEml($content, $depth + 1, $limits);
                $attachments[] = new MailFilePart(
                    name: FileAttacher::displayName($filename !== '' && $filename !== 'rfc822' ? $filename : ($nested->subject !== '' ? $nested->subject . '.eml' : 'mail.eml'), 'mail.eml'),
                    mime: MailFileFormat::Eml->canonicalMime(),
                    size: strlen($content),
                    message: $nested,
                );

                continue;
            }

            $contentId = self::normalizeContentId(isset($attributes['content_id']) ? (string) $attributes['content_id'] : null);
            $disposition = strtolower(trim((string) ($attachment->getDisposition() ?? '')));
            $attachments[] = self::filePart(
                $filename,
                $content,
                $contentType,
                $disposition === 'inline' || ($contentId !== null && $html !== null && stripos($html, 'cid:' . $contentId) !== false),
                $contentId,
            );
        }

        [$text, $fromHtml, $truncated] = self::bodyText($text, $html);

        return new MailFileMessage(
            format: MailFileFormat::Eml,
            subject: mb_scrub(trim((string) $message->getSubject()), 'UTF-8'),
            from: self::imapAddresses($message->getFrom())[0] ?? null,
            to: self::imapAddresses($message->getTo()),
            cc: self::imapAddresses($message->getCc()),
            bcc: self::imapAddresses($message->getBcc()),
            timestamp: self::imapTimestamp($message->getDate()),
            messageId: self::normalizeContentId((string) $message->getMessageId()),
            text: $text,
            textFromHtml: $fromHtml,
            textTruncated: $truncated,
            hasHtml: $html !== null && trim($html) !== '',
            attachments: $attachments,
            html: $html,
        );
    }

    private function fromToolkit(ToolkitMessage $message, MailFileFormat $format): MailFileMessage {
        $html = $message->getHtmlBody();
        [$text, $fromHtml, $truncated] = self::bodyText((string) $message->getTextBody(), $html);
        $recipients = static fn (array $list): array => array_values(array_map(
            static fn (Recipient $r): array => ['name' => $r->getName(), 'address' => $r->getAddress()],
            $list,
        ));
        $from = $message->getFrom();

        return new MailFileMessage(
            format: $format,
            subject: trim($message->getSubject()),
            from: $from !== null ? ['name' => $from->getName(), 'address' => $from->getAddress()] : null,
            to: $recipients($message->getTo()),
            cc: $recipients($message->getCc()),
            bcc: $recipients($message->getBcc()),
            timestamp: $message->getDate()?->getTimestamp(),
            messageId: self::normalizeContentId($message->getMessageId()),
            text: $text,
            textFromHtml: $fromHtml,
            textTruncated: $truncated,
            hasHtml: $html !== null && trim($html) !== '',
            hasRtf: $message->hasRtfBody(),
            attachments: array_map(fn (ToolkitAttachment $a): MailFilePart => $this->toolkitPart($a, $format), $message->getAttachments()),
            html: $html,
        );
    }

    private function toolkitPart(ToolkitAttachment $attachment, MailFileFormat $format): MailFilePart {
        $nested = $attachment->getMessage();
        if ($nested !== null) {
            $inner = $this->fromToolkit($nested, $format);
            $name = trim($attachment->getFilename()) !== '' ? $attachment->getFilename() : ($inner->subject !== '' ? $inner->subject . '.msg' : 'mail.msg');

            return new MailFilePart(
                name: FileAttacher::displayName($name, 'mail.msg'),
                mime: $format->canonicalMime(),
                size: $attachment->getSize(),
                message: $inner,
            );
        }

        return self::filePart($attachment->getFilename(), $attachment->getContent(), (string) $attachment->getMimeType(), $attachment->isInline(), self::normalizeContentId($attachment->getContentId()));
    }

    /** Datei-Anhang: bereinigter Name, Typ am Inhalt, Positivliste der Anhänge (sonst Download nur als `.bin`). */
    private static function filePart(string $filename, string $content, string $claimedMime, bool $inline, ?string $contentId): MailFilePart {
        $name = FileAttacher::displayName($filename);

        return new MailFilePart(
            name: $name,
            mime: self::detectedMime($content, $claimedMime),
            size: strlen($content),
            inline: $inline,
            contentId: $contentId,
            content: $content,
            safe: FileAttacher::contentRejection($content, $name) === null,
        );
    }

    /**
     * Grenzen für Anzahl und Gesamtgröße der Anhänge über alle Ebenen (`.eml`;
     * beim `.msg` zählt der Toolkit-Parser selbst).
     *
     * @param  array{bytes: int, attachments: int, attachment_bytes: int, depth: int}  $limits
     */
    private function countAttachment(int $size, array $limits): void {
        if (++$this->attachmentCount > $limits['attachments']) {
            throw new MailFileUnreadableException(MailFileFailure::TooManyAttachments);
        }
        $this->attachmentBytes += $size;
        if ($this->attachmentBytes > $limits['attachment_bytes']) {
            throw new MailFileUnreadableException(MailFileFailure::AttachmentsTooLarge);
        }
    }

    private function resetBudget(): void {
        $this->budget = ['header_bytes' => 0, 'addresses' => 0, 'copy' => 0, 'parts' => 0, 'containers' => 0];
    }

    private static function maxParts(): int {
        return max(50, self::limits()['attachments'] * 4);
    }

    /**
     * Misst eine Mail so, wie webklex sie zerlegt (Structure::detectParts/
     * parsePart, Header::getBoundary), aber linear und über Positionen statt
     * Kopien: Kopfbereich je Teil und zusammen, Adressen, Kopfzeilen je Teil
     * samt Kopieraufwand, Teile, Behälter, Verschachtelung und Länge der
     * Boundary. Die Boundary wird gesucht, sobald `boundary=` im Rohkopf steht
     * (wie webklex), nicht erst bei erkanntem multipart-Typ — sonst ließe sich
     * die Prüfung mit einer gefalteten Content-Type-Zeile umgehen.
     *
     * @return string Kopfbereich der Mail
     *
     * @throws MailFileUnreadableException
     */
    private function assertMimeStructure(string $blob, int $maxParts): string {
        if (! str_contains($blob, "\r\n")) {
            $blob = str_replace("\n", "\r\n", $blob);
        }
        $end = strpos($blob, "\r\n\r\n");
        $header = $end === false ? '' : substr($blob, 0, $end);
        $this->countHeader($header);

        $boundary = self::boundaryOf($header);
        if ($boundary !== null) {
            $this->scanParts($blob, strlen($header) + 4, strlen($blob), $boundary, 1, $maxParts);
        }

        return $header;
    }

    /**
     * Teile zwischen den Vorkommen von `--boundary` im Bereich [$start, $end),
     * wie `explode()` sie bilden würde.
     *
     * @throws MailFileUnreadableException
     */
    private function scanParts(string $blob, int $start, int $end, string $boundary, int $level, int $maxParts): void {
        if ($level > self::MAX_MIME_NESTING || ++$this->budget['containers'] > self::MAX_CONTAINERS) {
            throw new MailFileUnreadableException(MailFileFailure::TooComplex);
        }
        $length = strlen($boundary);
        if ($length < 1 || $length > self::MAX_BOUNDARY_LENGTH) {
            throw new MailFileUnreadableException(MailFileFailure::Malformed);
        }
        $delimiter = '--' . $boundary;
        $delimiterLength = strlen($delimiter);
        if ($start >= $end) {
            return;
        }
        // Erst zählen, dann zerlegen: ein Teppich aus Grenzen soll keine Millionen Teile erzeugen.
        if (substr_count($blob, $delimiter, $start, $end - $start) > 2 * $maxParts + 2) {
            throw new MailFileUnreadableException(MailFileFailure::TooComplex);
        }

        $pieceStart = $start;
        while (true) {
            $next = strpos($blob, $delimiter, $pieceStart);
            $pieceEnd = $next === false || $next + $delimiterLength > $end ? $end : $next;
            // webklex: substr($teil, 2) — CRLF bzw. „--“ hinter der Grenze.
            $this->scanPiece($blob, $pieceStart + 2, $pieceEnd, $level, $maxParts);
            if ($pieceEnd === $end) {
                return;
            }
            $pieceStart = $pieceEnd + $delimiterLength;
        }
    }

    /** @throws MailFileUnreadableException */
    private function scanPiece(string $blob, int $start, int $end, int $level, int $maxParts): void {
        $length = $end - $start;
        if ($length <= 0) {
            return;
        }
        if ($length === 2) {
            $two = substr($blob, $start, 2);
            if ($two === '--' || $two === "\r\n") {
                return;
            }
        }
        if (++$this->budget['parts'] > $maxParts) {
            throw new MailFileUnreadableException(MailFileFailure::TooComplex);
        }

        // Kopfbereich nur in einem begrenzten Fenster suchen — keine Kopie des ganzen Teils.
        $window = substr($blob, $start, min($length, self::MAX_HEADER_BYTES + 4));
        if (str_starts_with($window, "\r\n")) {
            $headerLength = 0;
        } else {
            $terminator = strpos($window, "\r\n\r\n");
            if ($terminator === false && $length > self::MAX_HEADER_BYTES) {
                throw new MailFileUnreadableException(MailFileFailure::TooComplex);
            }
            $headerLength = $terminator === false ? $length : $terminator + 2;
        }
        $header = substr($window, 0, $headerLength);

        // webklex streicht Zeile um Zeile bis zur Leerzeile und kopiert dabei jedes Mal den Rest des Teils.
        $lines = substr_count($header, "\r\n");
        $this->budget['copy'] += $lines * $length;
        if ($lines > self::MAX_PART_HEADER_LINES || $this->budget['copy'] > self::MAX_COPY_BYTES) {
            throw new MailFileUnreadableException(MailFileFailure::TooComplex);
        }
        $this->countHeader($header);

        $nested = self::boundaryOf($header);
        if ($nested !== null) {
            $this->scanParts($blob, $start + $headerLength, max($start + $headerLength, $end - 2), $nested, $level + 1, $maxParts);
        }
    }

    /**
     * Kopfbereich zählen: Größe je Teil und zusammen, Adressen in Adressköpfen
     * (webklex zerlegt Adresslisten mit einem Muster, das je Komma den Rest
     * durchsucht).
     *
     * @throws MailFileUnreadableException
     */
    private function countHeader(string $header): void {
        $size = strlen($header);
        $this->budget['header_bytes'] += $size;
        if ($size > self::MAX_HEADER_BYTES || $this->budget['header_bytes'] > self::MAX_TOTAL_HEADER_BYTES) {
            throw new MailFileUnreadableException(MailFileFailure::TooComplex);
        }
        if ($header === '') {
            return;
        }

        $unfolded = (string) preg_replace('/\r?\n[ \t]+/', ' ', $header);
        if (preg_match_all(self::ADDRESS_HEADER_PATTERN, $unfolded, $matches) > 0) {
            foreach ($matches[1] as $value) {
                $this->budget['addresses'] += max(substr_count($value, ','), substr_count($value, '@'), 0) + 1;
            }
        }
        if ($this->budget['addresses'] > self::MAX_ADDRESSES) {
            throw new MailFileUnreadableException(MailFileFailure::TooComplex);
        }
    }

    /** Boundary wie webklex (Header::getBoundary): erstes `boundary=` im Rohkopf, bereinigt. */
    private static function boundaryOf(string $header): ?string {
        if (preg_match('/boundary=(.*?(?=;)|(.*))/i', $header, $match) !== 1) {
            return null;
        }

        return str_replace(['"', '\r', '\n', "\n", "\r", ';', '\s'], '', (string) $match[1]);
    }

    /**
     * Text der Ansicht: Klartext-Körper, sonst Text aus dem HTML; gekürzt auf
     * {@see MAX_TEXT_CHARS}.
     *
     * @return array{0: string, 1: bool, 2: bool} Text, aus HTML, gekürzt
     */
    private static function bodyText(string $text, ?string $html): array {
        $fromHtml = false;
        if (trim($text) === '' && $html !== null && trim($html) !== '') {
            $text = self::htmlToText($html);
            $fromHtml = true;
        }
        $text = mb_scrub(str_replace(["\r\n", "\r"], "\n", $text), 'UTF-8');
        $truncated = mb_strlen($text) > self::MAX_TEXT_CHARS;

        return [$truncated ? mb_substr($text, 0, self::MAX_TEXT_CHARS) : trim($text, "\n"), $fromHtml, $truncated];
    }

    /** Am Inhalt erkannter Typ; ohne Erkennung die Angabe des Absenders, sonst octet-stream. */
    private static function detectedMime(string $content, string $claimed): string {
        $detected = $content !== '' ? File::mimeTypeFromContent($content) : false;
        $mime = $detected !== false && $detected !== '' ? $detected : trim(explode(';', $claimed, 2)[0]);

        return strtolower($mime !== '' ? $mime : 'application/octet-stream');
    }

    private static function normalizeContentId(?string $value): ?string {
        $value = trim((string) $value, " \t<>");

        return $value === '' ? null : mb_scrub($value, 'UTF-8');
    }

    /** @return list<array{name: string, address: string}> */
    private static function imapAddresses(mixed $attribute): array {
        if (! $attribute instanceof ImapAttribute) {
            return [];
        }
        $out = [];
        foreach ($attribute->all() as $address) {
            if ($address instanceof ImapAddress) {
                $out[] = ['name' => mb_scrub(trim($address->personal), 'UTF-8'), 'address' => mb_scrub(trim($address->mail), 'UTF-8')];
            }
        }

        return $out;
    }

    private static function imapTimestamp(mixed $attribute): ?int {
        $date = $attribute instanceof ImapAttribute ? $attribute->first() : null;
        if (! $date instanceof \DateTimeInterface || $date->getTimestamp() === 0) {
            return null;
        }

        return $date->getTimestamp();
    }

    private static function imapConfig(): ImapConfig {
        static $config = null;

        return $config ??= ImapConfig::make([
            'options' => ['fallback_date' => self::FALLBACK_DATE],
            'decoding' => ['options' => ['header' => 'iconv', 'message' => 'iconv', 'attachment' => 'iconv']],
        ]);
    }
}
