<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TranscribeDictationJob.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Media\Dictation;
use App\Services\Media\DictationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};

/** Diktat transkribieren und gliedern (MVP-1060) — auf der Medien-Queue wie die Untertitel. */
class TranscribeDictationJob implements ShouldQueue {
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(public readonly int $dictationId) {
        $this->onQueue('media');
        // Wie bei den Untertiteln: retry_after gilt je Verbindung.
        if (config('queue.default') !== 'sync') {
            $this->onConnection('media');
        }
    }

    public function handle(DictationService $dictations): void {
        $dictation = Dictation::query()->withoutGlobalScopes()->find($this->dictationId);
        if ($dictation !== null) {
            $dictations->process($dictation);
        }
    }
}
