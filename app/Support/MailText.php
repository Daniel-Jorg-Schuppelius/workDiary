<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MailText.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Support;

/**
 * Nutzertext für Markdown-Mail-Notifications entschärfen.
 *
 * `MailMessage::line()` rendert durch den CommonMark-Parser: HTML wird
 * escaped, aber Markdown-LINK-Syntax `[Text](https://…)` bleibt aktiv —
 * eine Spesen-Beschreibung oder ein Ereignistitel würde so zum klickbaren
 * Phishing-Link in einer legitimen System-Mail. Escapen der Klammern
 * (und des Backslashs gegen Umgehung) neutralisiert Link-/Bild-Syntax,
 * der Text bleibt ansonsten unverändert lesbar.
 */
final class MailText {
    public static function plain(?string $text): string {
        return str_replace(['\\', '[', ']'], ['\\\\', '\\[', '\\]'], (string) $text);
    }
}
