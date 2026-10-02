<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SpeechTranscriber.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Media;

use CommonToolkit\Helper\FileSystem\Folder;
use CommonToolkit\Helper\Media\MediaHelper;
use Illuminate\Support\Str;

/**
 * Lokale Spracherkennung über Whisper (MVP-1060) — eine Stelle für
 * Untertitel (Lernvideos) und Diktat. Läuft auf dem eigenen Server; das Audio
 * verlässt ihn nicht. Das Arbeitsverzeichnis ist ein Zwischenprodukt und wird
 * immer geräumt.
 */
class SpeechTranscriber {
    public function isAvailable(): bool {
        return MediaHelper::isWhisperAvailable();
    }

    /**
     * @param  string  $format  `txt` oder `vtt`
     * @return string|null Inhalt der Ausgabe oder null bei Fehler/Zeitüberschreitung
     */
    public function transcribe(string $sourcePath, string $locale, string $format = 'txt', ?float $timeout = null): ?string {
        // Der Toolkit-Helfer legt nur das Zielverzeichnis selbst an, nicht dessen Elternpfad.
        Folder::create(storage_path('app/tmp'), 0775, true);
        $workDir = storage_path('app/tmp/whisper-' . Str::random(12));

        try {
            return MediaHelper::transcribeWhisper(
                $sourcePath,
                $workDir,
                (string) config('media.transcription.model', 'base'),
                (string) config('media.transcription.model_dir', ''),
                $locale,
                'transcribe',
                (string) config('media.transcription.device', 'cpu'),
                $format,
                $timeout,
            );
        } finally {
            // Folder::delete wirft auf ein fehlendes Verzeichnis — das verdeckte sonst den eigentlichen Fehler.
            if (Folder::exists($workDir)) {
                Folder::delete($workDir, true);
            }
        }
    }
}
