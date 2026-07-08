<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MailboxGateway.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Mail;

use App\Models\EmailConnection;

/**
 * Transport-Abstraktion für ein Eingangspostfach (Feature 056, MVP-117): neue
 * (ungelesene) Nachrichten abrufen und eine verarbeitete Nachricht markieren
 * (als gelesen kennzeichnen bzw. verschieben — nie löschen). Kapselt IMAP; die
 * Intake-Logik hängt nur hieran und wird im Test gefaked (kein echter Server).
 */
interface MailboxGateway {
    /**
     * @return list<ParsedMessage>
     */
    public function fetch(EmailConnection $connection): array;

    /** Markiert die Nachricht als verarbeitet (gelesen/verschoben) — best effort. */
    public function markProcessed(EmailConnection $connection, ParsedMessage $message): void;
}
