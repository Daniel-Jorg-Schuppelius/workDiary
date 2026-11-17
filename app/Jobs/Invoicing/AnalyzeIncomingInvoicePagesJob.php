<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AnalyzeIncomingInvoicePagesJob.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Jobs\Invoicing;

use App\Models\Invoicing\IncomingEInvoice;
use App\Services\Invoicing\GiroCode\GiroCodeReader;
use App\Services\Invoicing\Split\IncomingInvoiceSplitter;
use App\Support\OrganizationContext;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\{ShouldBeUniqueUntilProcessing, ShouldQueue};
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Seitenarbeit am Rechnungseingang (Feature 208, MVP-1662/1663): Grenzen einer
 * Sammel-PDF vorschlagen und Vorschaubilder rastern, danach den GiroCode lesen.
 *
 * Immer in der Medien-Warteschlange auf der Medien-Verbindung — auch wenn die
 * Standard-Warteschlange `sync` ist: Rastern, OCR und QR-Lesen gehören nie in
 * den Request, und den Medien-Worker startet cron.sh auf jeder Installation.
 * Nur Unit-Tests rechnen synchron. Was an der Datei scheitert, scheitert beim
 * zweiten Mal genauso — eine Ausnahme beendet den Job ({@see $maxExceptions}).
 * Scheitert die Grenzerkennung, gilt der Eingang als eine Rechnung.
 *
 * Fairness zwischen Organisationen: je Organisation höchstens
 * {@see PER_ORGANIZATION_PER_MINUTE} Läufe je Minute; der Rest wird
 * zurückgestellt, damit ein Massen-Upload die Warteschlange nicht für alle
 * anderen belegt.
 */
final class AnalyzeIncomingInvoicePagesJob implements ShouldBeUniqueUntilProcessing, ShouldQueue {
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public const int PER_ORGANIZATION_PER_MINUTE = 10;

    public int $timeout = 1800;

    /** Zurückstellen durch die Fairness-Grenze ist kein Fehlschlag; eine Ausnahme schon. */
    public int $maxExceptions = 1;

    /** Hält die Eindeutigkeitssperre höchstens so lange, falls ein Worker stirbt. */
    public int $uniqueFor = 2 * IncomingInvoiceSplitter::JOB_TIMEOUT;

    public function __construct(
        public readonly int $incomingId,
        public readonly int $organizationId = 0,
    ) {
        $this->onQueue('media');

        if (! app()->runningUnitTests()) {
            $this->onConnection('media');
        }
    }

    public function uniqueId(): string {
        return (string) $this->incomingId;
    }

    public function retryUntil(): DateTimeInterface {
        return now()->addHours(12);
    }

    public function handle(IncomingInvoiceSplitter $splitter, GiroCodeReader $reader): void {
        $incoming = $this->incoming();
        if ($incoming === null || $incoming->organization === null) {
            return;
        }
        $key = 'incoming-pages:' . (int) $incoming->organization_id;
        if (RateLimiter::tooManyAttempts($key, self::PER_ORGANIZATION_PER_MINUTE)) {
            $this->release(RateLimiter::availableIn($key) + 1);

            return;
        }
        RateLimiter::hit($key, 60);

        OrganizationContext::run($incoming->organization, static function () use ($incoming, $splitter, $reader): void {
            $wasHolding = IncomingInvoiceSplitter::isHolding($incoming);
            if ($incoming->splitState() === IncomingInvoiceSplitter::PENDING) {
                $splitter->analyze($incoming);
            }
            // Eine Sammel-PDF liest ihren GiroCode erst als Teil — vorher gehörte er zu irgendeiner der Rechnungen.
            if (IncomingInvoiceSplitter::isHolding($incoming) || $incoming->isClosed()) {
                return;
            }
            if (GiroCodeReader::isAwaited($incoming)) {
                $reader->apply($incoming);
            }
            if ($wasHolding) {
                $splitter->releaseTransfer($incoming);
            }
        });
    }

    public function failed(?Throwable $e): void {
        $incoming = $this->incoming();
        if ($incoming !== null) {
            app(IncomingInvoiceSplitter::class)->markFailed($incoming);
        }
    }

    private function incoming(): ?IncomingEInvoice {
        // Ohne Global Scopes: der Worker hat keine gebundene Organisation.
        return IncomingEInvoice::query()->withoutGlobalScopes()->find($this->incomingId);
    }
}
