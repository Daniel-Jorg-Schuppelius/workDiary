<?php
/*
 * Created on   : Sat Sep 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentTextFailure.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Document;

/**
 * Warum eine Dokumentversion keinen Text liefert (MVP-819). Der Grund wird
 * festgehalten, damit ein zweiter Lauf nicht erneut in dieselbe OCR läuft.
 */
enum DocumentTextFailure: string {
    /** Datei fehlt auf dem Speicher. */
    case VersionMissing = 'version_missing';

    /** Format trägt keinen auslesbaren Text (Office, Archiv, Video …). */
    case Unsupported = 'unsupported';

    /** Auslesen lief, förderte aber nichts zutage (Bild ohne Schrift). */
    case Empty = 'empty';

    /** Auslesen selbst ist gescheitert (Toolkit-Fehler, defekte Datei). */
    case Failed = 'failed';

    /**
     * Kann ein zweiter Versuch etwas ändern? Die Datei kann zurückkehren und
     * ein fehlendes Werkzeug nachinstalliert werden; Format und leeres
     * Ergebnis bleiben dagegen, was sie sind.
     */
    public function retryable(): bool {
        return match ($this) {
            self::VersionMissing, self::Failed => true,
            self::Unsupported, self::Empty => false,
        };
    }

    /** @return list<string> Gründe, die der Nachlauf erneut versucht. */
    public static function retryableValues(): array {
        return array_values(array_map(
            static fn (self $case): string => $case->value,
            array_filter(self::cases(), static fn (self $case): bool => $case->retryable()),
        ));
    }

    /** Der KI-Vorschlagsdienst meldet den Grund als Text an die Person. */
    public function aiMessageKey(): string {
        return match ($this) {
            self::VersionMissing => 'ai.error.document_version_missing',
            self::Unsupported => 'ai.error.document_text_unsupported',
            self::Empty => 'ai.error.document_text_empty',
            self::Failed => 'ai.error.document_text_failed',
        };
    }
}
