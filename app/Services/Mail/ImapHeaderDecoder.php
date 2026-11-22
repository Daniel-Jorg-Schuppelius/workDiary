<?php
/*
 * Created on   : Sun Nov 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ImapHeaderDecoder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Mail;

use Webklex\PHPIMAP\Decoder\HeaderDecoder;

/**
 * Kopfzeilen-Decoder für `webklex/php-imap`, dessen Ergebnis nicht davon
 * abhängt, ob `ext-imap` geladen ist (Befund 2026-11-22).
 *
 * webklex verzweigt in den Kopfzeilen nach der Erweiterung:
 * - Der Standard-Decoder `utf-8` löst kodierte Wörter (RFC 2047) nur über
 *   `ext-imap` auf. Ohne die Erweiterung bleiben Betreff und Anzeigenamen roh
 *   (`=?UTF-8?Q?Angebot_f=C3=BCr?=`).
 * - Der Decoder `iconv` löst sie ohne Erweiterung richtig auf. Mit `ext-imap`
 *   dekodiert webklex Anzeigenamen aber vorab über `imap_mime_header_decode()`,
 *   und `iconv_mime_decode()` läuft ein zweites Mal über den fertigen Text:
 *   Jedes Nicht-ASCII-Zeichen fällt weg („Büro“ → „Bro“). Dasselbe trifft
 *   rohe UTF-8-Kopfzeilen (RFC 6532).
 *
 * Dieser Decoder zerlegt deshalb immer ohne `ext-imap` und dekodiert nur Text,
 * der ein kodiertes Wort enthält, genau einmal über iconv. Schon dekodierter
 * oder roher UTF-8-Text bleibt unverändert. Eingebunden über {@see DECODING}.
 */
final class ImapHeaderDecoder extends HeaderDecoder {
    /** Teil `decoding` der webklex-Konfiguration: dieser Decoder für Kopfzeilen, über iconv. */
    public const array DECODING = [
        'options' => ['header' => 'iconv'],
        'decoder' => ['header' => self::class],
    ];

    /** @param array<array-key, mixed>|string|null $value */
    public function decode(array|string|null $value, ?string $encoding = null): mixed {
        if (is_string($value) && ! str_contains($value, '=?')) {
            return $value;
        }

        return parent::decode($value, $encoding);
    }

    /**
     * Der Weg von webklex ohne `ext-imap`: Der Text bleibt kodiert, und
     * {@see decode()} löst ihn danach einmal auf.
     *
     * @return array<int, object{charset: string, text: string}>
     */
    public function mimeHeaderDecode(string $text): array {
        $charset = $this->getEncoding($text);

        return [(object) ['charset' => $charset, 'text' => (string) $this->convertEncoding($text, $charset)]];
    }
}
