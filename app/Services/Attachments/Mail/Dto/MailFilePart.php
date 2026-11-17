<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MailFilePart.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Attachments\Mail\Dto;

/**
 * Anhang einer gelesenen Mail-Datei (Feature 204, MVP-1652) — gleich, ob er
 * aus einer `.eml` oder einer `.msg` stammt. Eine eingebettete Mail steht in
 * {@see $message}; ihr Inhalt wird nicht als Datei angeboten.
 *
 * Der Typ ist der am Inhalt erkannte, nicht die Angabe des Absenders; die
 * Vorschau richtet sich nur danach.
 */
final class MailFilePart {
    /** Typen, die die vorhandene Vorschau zeigt (PDF und Rasterbilder, nie SVG). */
    public const array PREVIEW_MIMES = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    public function __construct(
        /** Bereinigter Anzeigename (ohne Pfad und Steuerzeichen). */
        public readonly string $name,
        /** Am Inhalt erkannter Typ (kleingeschrieben); bei eingebetteten Mails `message/rfc822`. */
        public readonly string $mime,
        public readonly int $size,
        public readonly bool $inline = false,
        public readonly ?string $contentId = null,
        public readonly ?MailFileMessage $message = null,
        /** Rohbytes — nie in der gespeicherten Zusammenfassung ({@see toArray()}). */
        public readonly string $content = '',
        /** Endung und Typ am Inhalt stehen auf der Positivliste der Anhänge; sonst nur als `.bin` ausliefern. */
        public readonly bool $safe = false,
    ) {}

    public function isMessage(): bool {
        return $this->message !== null;
    }

    /** Öffnet in der Vorschau (PDF, Bild) statt als Download. */
    public function isPreviewable(): bool {
        return ! $this->isMessage() && in_array($this->mime, self::PREVIEW_MIMES, true);
    }

    /** Name beim Herunterladen: ungeprüfte Typen nur als `.bin`, damit nichts mit fremder Endung landet. */
    public function downloadName(): string {
        return $this->safe ? $this->name : $this->name . '.bin';
    }

    /** @return array<string, mixed> Gespeicherte Form ohne Inhalt (nur Arrays und Skalare) */
    public function toArray(): array {
        return [
            'name' => $this->name,
            'mime' => $this->mime,
            'size' => $this->size,
            'inline' => $this->inline,
            'content_id' => $this->contentId,
            'safe' => $this->safe,
            'message' => $this->message?->toArray(),
        ];
    }

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self {
        return new self(
            name: (string) ($data['name'] ?? ''),
            mime: (string) ($data['mime'] ?? ''),
            size: (int) ($data['size'] ?? 0),
            inline: (bool) ($data['inline'] ?? false),
            contentId: isset($data['content_id']) && is_string($data['content_id']) ? $data['content_id'] : null,
            message: is_array($data['message'] ?? null) ? MailFileMessage::fromArray($data['message']) : null,
            safe: (bool) ($data['safe'] ?? false),
        );
    }
}
