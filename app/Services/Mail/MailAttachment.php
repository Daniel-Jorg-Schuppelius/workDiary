<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MailAttachment.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Mail;

/**
 * Ein bereits extrahierter E-Mail-Anhang (Feature 056, MVP-117 → Rang 7).
 * Transport-neutral: der IMAP-Adapter füllt Name/MIME/Inhalt, der
 * {@see MailAttachmentStore} prüft ihn gegen die Whitelist/Größen-Policy und
 * persistiert ihn beim Intake temporär. Der rohe Inhalt liegt als Byte-String
 * vor (im Test synthetisch, kein echter Server).
 */
final class MailAttachment {
    public function __construct(
        public readonly string $filename,
        public readonly string $mime,
        public readonly string $content,
    ) {}

    /** Größe in Bytes (aus dem Inhalt abgeleitet, keine separate Quelle). */
    public function size(): int {
        return strlen($this->content);
    }
}
