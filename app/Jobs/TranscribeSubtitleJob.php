<?php
/*
 * Created on   : Sun Aug 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TranscribeSubtitleJob.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Jobs;

use App\Models\{Attachment, User};
use App\Notifications\Media\SubtitleTranscribedNotification;
use App\Services\Media\VideoTranscodingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};

/**
 * Untertitel maschinell erzeugen (Feature 150).
 *
 * Wie das Transcoding in der Medien-Warteschlange und mit **einem** Versuch:
 * Whisper braucht auf der CPU ein Vielfaches der Spielzeit, und ein Lauf, der
 * an der Datei scheitert, scheitert beim zweiten Mal genauso.
 */
class TranscribeSubtitleJob implements ShouldQueue {
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(
        public readonly int $attachmentId,
        public readonly string $locale,
        public readonly int $requestedBy,
        public readonly ?string $url = null,
    ) {
        $this->onQueue('media');

        // Siehe TranscodeVideoJob: retry_after gilt pro Verbindung.
        if (config('queue.default') !== 'sync') {
            $this->onConnection('media');
        }
    }

    public function handle(VideoTranscodingService $service): void {
        $attachment = $this->attachment();

        if ($attachment === null) {
            return;
        }

        $service->transcribeSubtitle($attachment, $this->locale);

        $this->tell($attachment->original_name, null);
    }

    public function failed(?\Throwable $e): void {
        $attachment = $this->attachment();

        $this->tell(
            $attachment instanceof Attachment ? (string) $attachment->original_name : '',
            $e?->getMessage() ?: (string) __('media.errors.job_failed'),
        );
    }

    private function attachment(): ?Attachment {
        // Ohne Global Scopes: der Worker hat keine gebundene Organisation.
        return Attachment::query()->withoutGlobalScopes()->find($this->attachmentId);
    }

    private function tell(string $fileName, ?string $error): void {
        $user = User::query()->withoutGlobalScopes()->find($this->requestedBy);

        $user?->notify(new SubtitleTranscribedNotification($fileName, $this->locale, $error, $this->url));
    }
}
