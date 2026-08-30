<?php
/*
 * Created on   : Sun Aug 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TranscodeVideoJob.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\Media\MediaState;
use App\Models\Attachment;
use App\Services\Media\VideoTranscodingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};

/**
 * Video umrechnen (Feature 150).
 *
 * **Eigene Warteschlange, ein Worker.** ffmpeg läuft auf demselben Server
 * wie die Anwendung (Betreiber-Entscheidung 2026-08-29) — ein
 * 20-Minuten-Video lastet einen Kern minutenlang aus. Läge der Job in der
 * Standard-Warteschlange, blockierte er Mails, Benachrichtigungen und
 * Exporte hinter sich.
 *
 * **Ein Versuch, keine Wiederholung.** Ein gescheitertes Transcoding
 * scheitert beim zweiten Mal genauso (falsches Format, kaputte Datei, zu
 * lang) — es dreimal zu versuchen kostet nur dreimal die CPU-Zeit. Der Grund
 * steht am Anhang, die Oberfläche zeigt ihn an, und ein neuer Anlauf ist
 * eine bewusste Handlung.
 */
class TranscodeVideoJob implements ShouldQueue {
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Reichlich: ein langes Video darf rechnen, ohne dass der Job stirbt. */
    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(public readonly int $attachmentId) {
        $this->onQueue('media');

        // Eigene Verbindung, weil `retry_after` **pro Verbindung** gilt: die
        // Standard-Verbindung stellt nach 630 s erneut zu — ein einstündiges
        // Transcoding liefe dann doppelt, zwei ffmpeg-Läufe schrieben in
        // dieselbe Datei. Bei QUEUE_CONNECTION=sync (Entwicklung, Tests)
        // bleibt es synchron; eine eigene Verbindung legte den Job dort in
        // die Datenbank, wo ihn niemand abholt.
        if (config('queue.default') !== 'sync') {
            $this->onConnection('media');
        }
    }

    public function handle(VideoTranscodingService $service): void {
        // Ohne Global Scopes: der Worker hat keine gebundene Organisation.
        $attachment = Attachment::query()->withoutGlobalScopes()->find($this->attachmentId);

        if ($attachment === null) {
            return;
        }

        $service->process($attachment);
    }

    /** Stirbt der Job hart (Timeout, Speicher), bleibt der Zustand ehrlich. */
    public function failed(?\Throwable $e): void {
        $attachment = Attachment::query()->withoutGlobalScopes()->find($this->attachmentId);

        if ($attachment === null) {
            return;
        }

        $attachment->forceFill([
            'media_state' => MediaState::Failed,
            'media_error' => mb_substr((string) __('media.errors.job_failed'), 0, 255),
        ])->save();
    }
}
