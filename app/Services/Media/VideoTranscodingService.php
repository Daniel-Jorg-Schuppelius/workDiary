<?php
/*
 * Created on   : Sun Aug 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VideoTranscodingService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Media;

use App\Enums\Media\{MediaRenditionKind, MediaState, SubtitleSource};
use App\Models\{Attachment, User};
use App\Models\Media\MediaRendition;
use App\Services\Licensing\LimitGuard;
use CommonToolkit\Helper\FileSystem\Folder;
use CommonToolkit\Helper\Media\MediaHelper;
use Illuminate\Support\{Carbon, Str};
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Videos in abspielbare Fassungen umrechnen (Feature 150).
 *
 * **ffmpeg kommt aus dem Toolkit** (`CommonToolkit\Helper\Media\MediaHelper`)
 * — hier steht kein einziger Shell-Aufruf. Der Helfer kennt die
 * Verfügbarkeitsprüfung, das Escaping und das Auslesen der Videodaten.
 *
 * **Warum überhaupt umrechnen:** Handy-Aufnahmen sind groß und liegen oft
 * in HEVC/MOV vor — das spielt nicht in jedem Browser, und über Mobilfunk
 * auf der Baustelle ist es unbrauchbar.
 *
 * **Betreiber-Entscheidungen vom 2026-08-29:**
 *  - ffmpeg läuft auf **demselben Server**. Deshalb gehört der Aufruf in
 *    eine eigene Warteschlange, nie in den Request.
 *  - Das **Original bleibt** erhalten, damit ein späterer Formatwechsel aus
 *    der Ausgangsqualität rechnen kann.
 *  - **Alles zählt** gegen die Speicherquote — deshalb wird der erwartete
 *    Gesamtbedarf **vor** dem Rechnen geprüft, nicht danach. Eine Quote, die
 *    erst nach zwanzig Minuten CPU-Zeit anschlägt, ist keine Quote.
 */
class VideoTranscodingService {
    /**
     * Zielauflösungen. Die Höhe entscheidet; die Breite folgt dem
     * Seitenverhältnis (`-2` hält sie gerade — ungerade Breiten lehnt H.264
     * ab).
     *
     * @var array<string, int>
     */
    public const VARIANTS = [
        '480p' => 480,
        '720p' => 720,
        '1080p' => 1080,
    ];

    /**
     * Obergrenze der Laufzeit. Ohne sie legt ein versehentlich hochgeladenes
     * Zwei-Stunden-Video den Server für eine Stunde lahm — auf demselben
     * Rechner, auf dem die Anwendung läuft.
     */
    public const MAX_DURATION_SECONDS = 3600;

    /** Grobe Schätzung des Platzbedarfs je Ableitung, Anteil am Original. */
    private const SIZE_FACTOR = [
        '480p' => 0.25,
        '720p' => 0.45,
        '1080p' => 0.80,
    ];

    public function __construct(
        private readonly LimitGuard $limits,
    ) {}

    /** Ist Transcoding auf diesem Server überhaupt möglich? */
    public function isAvailable(): bool {
        return MediaHelper::isFfmpegAvailable();
    }

    /**
     * Erwarteter Gesamtbedarf der Ableitungen in Bytes.
     *
     * Bewusst eine **Schätzung vor dem Rechnen**: die genaue Größe kennt man
     * erst hinterher, und dann ist die CPU-Zeit schon verbraucht.
     *
     * @param  list<string>  $variants
     */
    public function estimatedBytes(int $originalBytes, array $variants): int {
        $sum = 0;

        foreach ($variants as $variant) {
            $sum += (int) round($originalBytes * (self::SIZE_FACTOR[$variant] ?? 0.5));
        }

        // Vorschaubild grob mitgezählt.
        return $sum + 200_000;
    }

    /**
     * Welche Auflösungen ergeben Sinn?
     *
     * **Nie hochrechnen**: aus einem 480p-Video ein 1080p zu machen kostet
     * Platz und Rechenzeit und sieht schlechter aus als das Original.
     *
     * @return list<string>
     */
    public function variantsFor(int $sourceHeight): array {
        $out = [];

        foreach (self::VARIANTS as $name => $height) {
            if ($height <= $sourceHeight) {
                $out[] = $name;
            }
        }

        // Ist die Quelle kleiner als die kleinste Stufe, bleibt genau eine
        // Fassung: das Format normalisieren muss trotzdem sein.
        return $out !== [] ? $out : ['480p'];
    }

    /**
     * Video verarbeiten: Daten lesen, Auflösungen rechnen, Vorschaubild
     * ziehen.
     *
     * Läuft im Warteschlangen-Job, nie im Request.
     */
    public function process(Attachment $attachment): Attachment {
        if (! $this->isAvailable()) {
            return $this->fail($attachment, (string) __('media.errors.ffmpeg_missing'));
        }

        $disk = Storage::disk($attachment->disk);
        $source = $disk->path($attachment->path);

        if (! is_file($source)) {
            return $this->fail($attachment, (string) __('media.errors.source_missing'));
        }

        $info = MediaHelper::getVideoInfo($source);

        if ($info === null) {
            return $this->fail($attachment, (string) __('media.errors.unreadable'));
        }

        $duration = (int) round((float) ($info['duration'] ?? 0));
        $height = (int) ($info['height'] ?? 0);
        $width = (int) ($info['width'] ?? 0);

        if ($duration > self::MAX_DURATION_SECONDS) {
            return $this->fail($attachment, (string) __('media.errors.too_long', [
                'minutes' => intdiv(self::MAX_DURATION_SECONDS, 60),
            ]));
        }

        $variants = $this->variantsFor($height);

        // Quote VOR dem Rechnen — sonst schlägt sie erst zu, wenn die
        // CPU-Zeit schon verbraucht ist.
        try {
            $organization = $attachment->organization;

            if ($organization !== null) {
                $this->limits->ensureCanStoreAttachment(
                    $organization,
                    $this->estimatedBytes((int) $attachment->size, $variants),
                );
            }
        } catch (\Throwable $e) {
            return $this->fail($attachment, $e->getMessage());
        }

        $attachment->forceFill([
            'media_state' => MediaState::Processing,
            'media_duration_seconds' => $duration,
            'media_width' => $width ?: null,
            'media_height' => $height ?: null,
            'media_error' => null,
        ])->save();

        $folder = dirname($attachment->path) . '/renditions/' . $attachment->id;
        $absoluteFolder = $disk->path($folder);

        if (! is_dir($absoluteFolder) && ! mkdir($absoluteFolder, 0775, true) && ! is_dir($absoluteFolder)) {
            return $this->fail($attachment, (string) __('media.errors.target_unwritable'));
        }

        $made = 0;

        foreach ($variants as $variant) {
            if ($this->renderVariant($attachment, $source, $folder, $absoluteFolder, $variant)) {
                $made++;
            }
        }

        if ($made === 0) {
            return $this->fail($attachment, (string) __('media.errors.no_rendition'));
        }

        $this->renderPoster($attachment, $source, $folder, $absoluteFolder);

        $attachment->forceFill([
            'media_state' => MediaState::Ready,
            'media_processed_at' => Carbon::now(),
        ])->save();

        return $attachment->refresh();
    }

    /** Untertitelspur von Hand hinterlegen (WebVTT). */
    public function attachSubtitle(Attachment $attachment, string $vtt, string $locale): MediaRendition {
        if (! str_contains(substr($vtt, 0, 20), 'WEBVTT')) {
            throw ValidationException::withMessages([
                'file' => (string) __('media.errors.not_webvtt'),
            ]);
        }

        return $this->putSubtitle($attachment, $vtt, $locale, SubtitleSource::Manual);
    }

    /** Ist maschinelle Spracherkennung auf diesem Server eingerichtet? */
    public function isTranscriptionAvailable(): bool {
        return MediaHelper::isWhisperAvailable();
    }

    /**
     * Untertitelspur maschinell erzeugen (Whisper, lokal).
     *
     * **Das Ergebnis ist ein Entwurf.** Whisper verhört sich bei Namen,
     * Fachbegriffen und Zahlen — also genau bei dem, was eine Unterweisung
     * trägt. Die Spur wird deshalb als `machine` ohne Durchsicht abgelegt und
     * bleibt auch so gekennzeichnet, bis ein Mensch sie freigibt (WCAG 1.2.2).
     *
     * Läuft nur im Warteschlangen-Job: eine Viertelstunde Video kostet auf
     * der CPU ein Vielfaches der Spielzeit.
     */
    public function transcribeSubtitle(Attachment $attachment, string $locale): MediaRendition {
        if (! $this->isTranscriptionAvailable()) {
            throw new RuntimeException((string) __('media.errors.whisper_missing'));
        }

        $disk = Storage::disk($attachment->disk);
        $source = $disk->path($attachment->path);

        if (! is_file($source)) {
            throw new RuntimeException((string) __('media.errors.source_missing'));
        }

        if ((int) ($attachment->media_duration_seconds ?? 0) > self::MAX_DURATION_SECONDS) {
            throw new RuntimeException((string) __('media.errors.too_long', [
                'minutes' => intdiv(self::MAX_DURATION_SECONDS, 60),
            ]));
        }

        // Der Toolkit-Helfer legt nur das Zielverzeichnis selbst an, nicht
        // dessen Elternpfad — auf einer frischen Installation gibt es
        // storage/app/tmp noch nicht.
        Folder::create(storage_path('app/tmp'), 0775, true);

        $workDir = storage_path('app/tmp/whisper-' . $attachment->id . '-' . Str::random(8));

        try {
            $vtt = MediaHelper::transcribeWhisper(
                $source,
                $workDir,
                (string) config('media.transcription.model', 'base'),
                (string) config('media.transcription.model_dir', ''),
                $locale,
                'transcribe',
                (string) config('media.transcription.device', 'cpu'),
                'vtt',
            );
        } finally {
            // Die Rohausgabe ist ein Zwischenprodukt; sie hat im Dauerspeicher
            // nichts verloren. Die Prüfung muss sein: Folder::delete wirft auf
            // ein fehlendes Verzeichnis — im finally-Zweig würde diese
            // Ausnahme den eigentlichen Fehler verdecken.
            if (is_dir($workDir)) {
                Folder::delete($workDir, true);
            }
        }

        // Kommt hier kein WebVTT an, liegt fast immer eine zu alte
        // Toolkit-Fassung ohne wählbares Ausgabeformat darunter — dann
        // schreibt Whisper Fließtext, und der ist als Spur wertlos.
        if ($vtt === null || ! str_contains(substr($vtt, 0, 20), 'WEBVTT')) {
            throw new RuntimeException((string) __('media.errors.transcription_failed'));
        }

        return $this->putSubtitle($attachment, $vtt, $locale, SubtitleSource::Machine);
    }

    /**
     * Durchsicht bestätigen.
     *
     * Erst damit ist eine maschinelle Spur ein Barrierefreiheitsnachweis —
     * und deshalb steht auch dran, wer sie gelesen hat.
     */
    public function markSubtitleReviewed(MediaRendition $rendition, User $reviewer): MediaRendition {
        if ($rendition->kind !== MediaRenditionKind::Subtitle) {
            throw new RuntimeException((string) __('media.errors.not_a_subtitle'));
        }

        $rendition->forceFill([
            'reviewed_at' => Carbon::now(),
            'reviewed_by' => $reviewer->id,
        ])->save();

        return $rendition->refresh();
    }

    /**
     * Untertitelspur verwerfen.
     *
     * Ohne diesen Weg bliebe eine misslungene Maschinenspur für immer am
     * Video hängen und würde weiter ausgespielt.
     */
    public function deleteSubtitle(MediaRendition $rendition): void {
        if ($rendition->kind !== MediaRenditionKind::Subtitle) {
            throw new RuntimeException((string) __('media.errors.not_a_subtitle'));
        }

        Storage::disk($rendition->disk)->delete($rendition->path);

        $rendition->delete();
    }

    private function putSubtitle(Attachment $attachment, string $vtt, string $locale, SubtitleSource $source): MediaRendition {
        $disk = Storage::disk($attachment->disk);
        $path = dirname($attachment->path) . '/renditions/' . $attachment->id . '/' . $locale . '.vtt';

        $disk->put($path, $vtt);

        return MediaRendition::query()->updateOrCreate(
            [
                'attachment_id' => $attachment->id,
                'kind' => MediaRenditionKind::Subtitle->value,
                'variant' => null,
                'locale' => $locale,
            ],
            [
                'organization_id' => $attachment->organization_id,
                'disk' => $attachment->disk,
                'path' => $path,
                'mime' => 'text/vtt',
                'size_bytes' => strlen($vtt),
                'source' => $source->value,
                // Eine ersetzte Spur ist eine neue Spur: die alte Durchsicht
                // gilt nicht für einen anderen Text.
                'reviewed_at' => null,
                'reviewed_by' => null,
            ]
        );
    }

    private function renderVariant(Attachment $attachment, string $source, string $folder, string $absoluteFolder, string $variant): bool {
        $height = self::VARIANTS[$variant];
        $target = $absoluteFolder . '/' . $variant . '.mp4';

        $args = [
            '-vf', 'scale=-2:' . $height,
            '-c:v', 'libx264',
            '-preset', 'medium',
            '-crf', '23',
            '-c:a', 'aac',
            '-b:a', '128k',
            '-movflags', '+faststart',
        ];

        $output = [];
        $code = 0;

        // stripVideo: false — wir wollen ausdrücklich das Bild behalten.
        $ok = MediaHelper::convert($source, $target, $args, $output, $code, false);

        if (! $ok || ! is_file($target)) {
            return false;
        }

        MediaRendition::query()->updateOrCreate(
            [
                'attachment_id' => $attachment->id,
                'kind' => MediaRenditionKind::Video->value,
                'variant' => $variant,
                'locale' => null,
            ],
            [
                'organization_id' => $attachment->organization_id,
                'disk' => $attachment->disk,
                'path' => $folder . '/' . $variant . '.mp4',
                'mime' => 'video/mp4',
                'size_bytes' => (int) filesize($target),
                'height' => $height,
            ]
        );

        return true;
    }

    private function renderPoster(Attachment $attachment, string $source, string $folder, string $absoluteFolder): void {
        $target = $absoluteFolder . '/poster.jpg';

        // Eine Sekunde hinein: das erste Bild ist oft schwarz.
        $args = ['-ss', '00:00:01', '-frames:v', '1', '-q:v', '3'];

        $output = [];
        $code = 0;

        if (! MediaHelper::convert($source, $target, $args, $output, $code, false) || ! is_file($target)) {
            return;
        }

        MediaRendition::query()->updateOrCreate(
            [
                'attachment_id' => $attachment->id,
                'kind' => MediaRenditionKind::Poster->value,
                'variant' => null,
                'locale' => null,
            ],
            [
                'organization_id' => $attachment->organization_id,
                'disk' => $attachment->disk,
                'path' => $folder . '/poster.jpg',
                'mime' => 'image/jpeg',
                'size_bytes' => (int) filesize($target),
            ]
        );
    }

    /**
     * Fehler festhalten statt zu werfen: der Warteschlangen-Job soll nicht
     * endlos wiederholen, und die Oberfläche muss sagen können, WAS
     * schiefging.
     */
    private function fail(Attachment $attachment, string $message): Attachment {
        $attachment->forceFill([
            'media_state' => MediaState::Failed,
            'media_error' => mb_substr($message, 0, 255),
            'media_processed_at' => Carbon::now(),
        ])->save();

        return $attachment->refresh();
    }
}
