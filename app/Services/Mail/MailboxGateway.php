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

use App\Models\Mail\EmailConnection;
use Generator;

/**
 * Transport-Abstraktion für ein Eingangspostfach (Feature 056, MVP-117): neue
 * Nachrichten ab dem Abrufstand liefern und eine verarbeitete Nachricht
 * markieren (als gelesen kennzeichnen bzw. verschieben — nie löschen). Seit
 * MVP-1139 entscheidet der Stand, nicht der Lesestatus. Die Intake-Logik hängt
 * nur hieran und wird im Test gefaked (kein echter Server).
 */
interface MailboxGateway {
    /**
     * Nachrichten ab dem Stand, höchstens `$limit`, einzeln geladen. Jede trägt
     * in `cursorAfter` den Stand nach ihr (Graph nur am Seitenende); der
     * Rückgabewert des Generators ist der Stand nach dem ganzen Durchlauf.
     *
     * @return Generator<int, ParsedMessage, mixed, MailboxCursor>
     *
     * @throws \App\Exceptions\MailboxFolderMissingException
     * @throws \App\Exceptions\MailboxUnavailableException
     */
    public function fetch(EmailConnection $connection, MailboxCursor $cursor, int $limit): Generator;

    /**
     * Markiert die Nachricht als verarbeitet (gelesen/verschoben) — best effort.
     *
     * @throws \App\Exceptions\MailboxFolderMissingException wenn der Verarbeitet-Ordner fehlt
     */
    public function markProcessed(EmailConnection $connection, ParsedMessage $message): void;

    /**
     * Ordnerpfade des Postfachs mit `/` als Trenner (Auswahl im Dialog).
     *
     * @return list<string>
     */
    public function folders(EmailConnection $connection): array;

    /**
     * Eine bereits abgerufene Nachricht erneut lesen (Message-ID mit oder ohne
     * spitze Klammern), etwa um ihren Text neu umzuwandeln; gesucht wird im
     * Eingangs- und im Verarbeitet-Ordner. null, wenn das Postfach sie nicht mehr hat.
     */
    public function find(EmailConnection $connection, string $messageId): ?ParsedMessage;
}
