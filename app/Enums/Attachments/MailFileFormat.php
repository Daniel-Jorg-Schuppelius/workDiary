<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MailFileFormat.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Attachments;

/**
 * Format einer abgelegten Mail-Datei (Feature 204, MVP-1652/1653): `.eml`
 * (MIME-Text, RFC 5322) oder `.msg` (Outlook, Compound File).
 *
 * Endung und am Inhalt erkannter Typ gehören zusammen: ein Compound File ist
 * nur als `.msg` zulässig und nur, wenn Fileinfo es als Outlook-Nachricht
 * erkennt — Word- und Excel-Dateien (ebenfalls Compound Files) und
 * unspezifische Compound Files nie. Gelesen wird die Datei erst danach und
 * asynchron ({@see \App\Jobs\Attachments\ReadMailAttachmentJob}).
 */
enum MailFileFormat: string {
    case Eml = 'eml';
    case Msg = 'msg';

    /** Endung (ohne Punkt, beliebige Schreibweise) → Format; andere Endungen → null. */
    public static function fromExtension(string $extension): ?self {
        return self::tryFrom(strtolower(trim($extension, '. ')));
    }

    /**
     * Format eines gespeicherten Anhangs. Der gespeicherte Typ stammt aus der
     * Inhaltsprüfung: `message/rfc822` und `application/vnd.ms-outlook` sind
     * eindeutig, Text und unspezifische Compound Files nur mit passender Endung.
     */
    public static function detect(string $originalName, ?string $mime): ?self {
        $mime = strtolower(trim((string) $mime));
        $byName = self::fromExtension((string) pathinfo($originalName, PATHINFO_EXTENSION));

        return match (true) {
            $mime === self::Eml->canonicalMime() => self::Eml,
            $mime === self::Msg->canonicalMime() => self::Msg,
            $byName !== null && in_array($mime, $byName->detectedMimes(), true) => $byName,
            default => null,
        };
    }

    /**
     * Am Inhalt erkannte Typen (Fileinfo, kleingeschrieben), die für diese
     * Endung in Frage kommen.
     *
     * @return list<string>
     */
    public function detectedMimes(): array {
        return match ($this) {
            // Fileinfo erkennt eine Mail nur an typischen ersten Kopfzeilen
            // (Received, From, Date …); beginnt sie mit MIME-Version oder
            // X-Kopfzeilen, meldet es Text. Den Rest prüft der Leser.
            self::Eml => ['message/rfc822', 'text/plain'],
            // Nur was libmagic am Verzeichnis als Outlook-Nachricht erkennt — kein
            // unspezifisches Compound File, nie msword/vnd.ms-excel/vnd.ms-powerpoint.
            self::Msg => ['application/vnd.ms-outlook'],
        };
    }

    /** Typ, unter dem der Anhang abgelegt und ausgeliefert wird. */
    public function canonicalMime(): string {
        return match ($this) {
            self::Eml => 'message/rfc822',
            self::Msg => 'application/vnd.ms-outlook',
        };
    }
}
