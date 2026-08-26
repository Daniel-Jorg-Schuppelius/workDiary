<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SmsText.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Notification\Sms;

use App\Enums\Notification\NotificationEvent;
use App\Support\NotificationText;
use CommonToolkit\Helper\Data\StringHelper;

/**
 * SMS-Kurztext einer Benachrichtigung (Feature 147, MVP-730).
 *
 * Zwei Regeln, die nirgends sonst gelten und deshalb hier liegen:
 *
 * 1. **Render-time**: Titel/Text werden aus `title_key`/`message_key` in der
 *    Sprache des EMPFÄNGERS gerendert ({@see NotificationText}) — der Versand
 *    läuft im Scheduler/in der Queue, wo die App-Default-Locale gilt; ein
 *    vorgerenderter Text wäre für alle deutsch.
 * 2. **Ein Segment**: mehr als eine SMS je Alarm kostet ein Vielfaches und
 *    kommt bei schlechtem Netz zerstückelt an. Die Grenze hängt an der
 *    Kodierung — 160 Zeichen im GSM-7-Alphabet, aber nur 70, sobald EIN
 *    Zeichen außerhalb liegt (Emoji, „…", kyrillisch). Deshalb wird die
 *    Kodierung geprüft, bevor gekürzt wird, und das Kürzungszeichen selbst
 *    darf die Kodierung nicht kippen.
 */
final class SmsText {
    /** Nutzlast eines GSM-7-Segments. */
    public const LIMIT_GSM7 = 160;

    /** Nutzlast eines UCS-2-Segments (sobald ein Zeichen außerhalb GSM-7 liegt). */
    public const LIMIT_UCS2 = 70;

    /**
     * GSM 03.38 Basiszeichensatz + Erweiterungstabelle, ohne Steuerzeichen
     * (die kommen in {@see isGsm7()} dazu). Bewusst einfach zitiert: in
     * doppelten Anführungszeichen läse PHP `$¥…` als Variable.
     *
     * Die Erweiterungszeichen kosten zwei Septette; das ist hier bewusst nicht
     * mitgezählt — die Kürzung liegt dadurch auf der sicheren Seite, nie darüber.
     */
    private const GSM7_BASE = '@£$¥èéùìòÇØøÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !"#¤%&\'()*+,-./0123456789:;<=>?'
        . '¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà'
        . '^{}\\[~]|€';

    /**
     * Fertiger Nachrichtentext für einen Empfänger: „Titel — Text", auf ein
     * Segment gekürzt. Ohne eigenen Text bleibt das Ereignis-Label stehen,
     * damit nie eine leere SMS rausgeht.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function forEvent(NotificationEvent $event, array $payload, ?int $limit = null): string {
        $title = StringHelper::normalizeWhitespace(NotificationText::title($payload));
        $message = StringHelper::normalizeWhitespace(NotificationText::message($payload));

        if ($title === '') {
            $title = (string) $event->label();
        }

        // Trenner bewusst als schlichter Bindestrich: ein Geviertstrich („—")
        // steht NICHT im GSM-7-Alphabet und würde jede zusammengesetzte
        // Nachricht auf UCS-2 und damit von 160 auf 70 Zeichen drücken.
        $text = $message !== '' && $message !== $title ? $title . ' - ' . $message : $title;

        return self::shorten($text, $limit);
    }

    /**
     * Kürzt auf ein Segment — an der letzten Wortgrenze, damit die Nachricht
     * nicht mitten im Wort abbricht. Fällt die Wortgrenze zu weit nach vorn
     * (ein sehr langes Wort), wird hart geschnitten.
     */
    public static function shorten(string $text, ?int $limit = null): string {
        $text = StringHelper::normalizeWhitespace($text);
        $limit = max(20, min($limit ?? self::configuredLimit(), self::segmentLimit($text)));

        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        // Das Kürzungszeichen darf die Kodierung nicht kippen: „…" ist kein
        // GSM-7-Zeichen und würde das Limit von 160 auf 70 drücken.
        $suffix = self::isGsm7($text) ? '...' : '…';
        $keep = $limit - mb_strlen($suffix);
        $cut = mb_substr($text, 0, $keep);

        $lastSpace = mb_strrpos($cut, ' ');
        if ($lastSpace !== false && $lastSpace >= (int) floor($keep * 0.6)) {
            $cut = mb_substr($cut, 0, $lastSpace);
        }

        // rtrim() mit Zeichenliste arbeitet BYTEweise: „–"/„—" sind
        // mehrbyteig, ihre Endbytes würden mitten aus einem UTF-8-Zeichen
        // geschnitten und der Text wäre kaputt (preg_split('//u') liefert dann
        // false — und die Kodierungsprüfung hielte alles für GSM-7).
        $cut = preg_replace('/[\s,.;:\-–—]+$/u', '', $cut) ?? $cut;

        return $cut . $suffix;
    }

    /** Zeichen je Segment für diesen Text (Kodierung entscheidet). */
    public static function segmentLimit(string $text): int {
        return self::isGsm7($text) ? self::LIMIT_GSM7 : self::LIMIT_UCS2;
    }

    /** Liegt der Text vollständig im GSM-7-Alphabet? */
    public static function isGsm7(string $text): bool {
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if ($chars === false) {
            // Kein gültiges UTF-8 — dann lieber die kleinere UCS-2-Grenze
            // annehmen als eine zu lange Nachricht zu bauen.
            return false;
        }

        $allowed = preg_split('//u', self::GSM7_BASE, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $allowed[] = "\n";
        $allowed[] = "\r";

        foreach ($chars as $char) {
            if (! in_array($char, $allowed, true)) {
                return false;
            }
        }

        return true;
    }

    /** Org-/Systemgrenze aus der Konfiguration (nie über einem Segment). */
    private static function configuredLimit(): int {
        return max(20, min(self::LIMIT_GSM7, (int) config('notifications.sms.body_truncate', self::LIMIT_GSM7)));
    }
}
