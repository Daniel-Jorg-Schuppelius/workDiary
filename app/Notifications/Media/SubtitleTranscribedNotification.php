<?php
/*
 * Created on   : Sun Aug 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SubtitleTranscribedNotification.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Notifications\Media;

use App\Notifications\DirectNotification;
use App\Support\NotificationText;

/**
 * Rückmeldung der maschinellen Untertitelung (Feature 150).
 *
 * Die Erkennung läuft in der Warteschlange und dauert länger als eine
 * Sitzung: wer sie angestoßen hat, hat die Seite längst verlassen. Ohne
 * diese Nachricht bliebe das Ergebnis — Erfolg wie Fehlschlag — unsichtbar.
 * Nur Datenbank-Kanal: eine Mail je Untertitelspur wäre Lärm.
 */
class SubtitleTranscribedNotification extends DirectNotification {
    private const TITLE_OK = 'Untertitel erzeugt';

    private const TITLE_FAILED = 'Untertitel fehlgeschlagen';

    private const MESSAGE_OK = 'Für „:file" liegt eine maschinelle Untertitelspur (:locale) bereit. Sie zählt erst nach Durchsicht.';

    private const MESSAGE_FAILED = 'Für „:file" ließ sich keine Untertitelspur erzeugen: :reason';

    public function __construct(
        public readonly string $fileName,
        // Nicht `$locale`: Illuminate\Notifications\Notification hat bereits
        // eine gleichnamige, beschreibbare Eigenschaft für die Versandsprache.
        public readonly string $trackLocale,
        public readonly ?string $error = null,
        public readonly ?string $url = null,
    ) {
        parent::__construct(['database']);
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array {
        unset($notifiable);

        $failed = $this->error !== null;

        $titleKey = $failed ? self::TITLE_FAILED : self::TITLE_OK;
        $messageKey = $failed ? self::MESSAGE_FAILED : self::MESSAGE_OK;

        $messageParams = $failed
            ? ['file' => $this->fileName, 'reason' => $this->error]
            : ['file' => $this->fileName, 'locale' => strtoupper($this->trackLocale)];

        return [
            'title' => NotificationText::render($titleKey, []),
            'title_key' => $titleKey,
            'title_params' => [],
            'message' => NotificationText::render($messageKey, $messageParams),
            'message_key' => $messageKey,
            'message_params' => $messageParams,
            'url' => $this->url,
            'icon' => 'subtitles',
        ];
    }
}
