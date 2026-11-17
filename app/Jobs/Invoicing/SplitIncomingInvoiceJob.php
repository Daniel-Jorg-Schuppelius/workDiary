<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SplitIncomingInvoiceJob.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Jobs\Invoicing;

use App\Models\Invoicing\IncomingEInvoice;
use App\Models\Platform\User;
use App\Services\Invoicing\Split\IncomingInvoiceSplitter;
use App\Support\OrganizationContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\{ShouldBeUniqueUntilProcessing, ShouldQueue};
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Throwable;

/**
 * Bestätigte Sammel-PDF aufteilen (Feature 208, MVP-1662): Teil-PDFs
 * herauslösen und je Rechnung erkennen — OCR und KI-Rückfall der Erkennung
 * laufen je Teil, deshalb immer auf der Medien-Verbindung (auch bei
 * Standard-Warteschlange `sync`) und nie im Request; nur Unit-Tests rechnen
 * synchron.
 */
final class SplitIncomingInvoiceJob implements ShouldBeUniqueUntilProcessing, ShouldQueue {
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1800;

    public int $tries = 1;

    /** Hält die Eindeutigkeitssperre höchstens so lange, falls ein Worker stirbt. */
    public int $uniqueFor = 2 * IncomingInvoiceSplitter::JOB_TIMEOUT;

    /** @param  list<int>  $starts */
    public function __construct(
        public readonly int $incomingId,
        public readonly int $actorId,
        public readonly array $starts,
    ) {
        $this->onQueue('media');

        if (! app()->runningUnitTests()) {
            $this->onConnection('media');
        }
    }

    public function uniqueId(): string {
        return (string) $this->incomingId;
    }

    public function handle(IncomingInvoiceSplitter $splitter): void {
        $incoming = IncomingEInvoice::query()->withoutGlobalScopes()->find($this->incomingId);
        $actor = User::query()->withoutGlobalScopes()->find($this->actorId);
        if ($incoming === null || $incoming->organization === null) {
            return;
        }
        if ($actor === null) {
            // Ohne handelnde Person kein Teilen — aber auch kein dauerhaftes „läuft“.
            $splitter->abandon($incoming, (string) __('incoming_pdf.split.error.failed'));

            return;
        }

        OrganizationContext::run($incoming->organization, fn (): mixed => $splitter->split($incoming, $this->starts, $actor));
    }

    public function failed(?Throwable $e): void {
        $incoming = IncomingEInvoice::query()->withoutGlobalScopes()->find($this->incomingId);
        if ($incoming !== null) {
            app(IncomingInvoiceSplitter::class)->abandon($incoming, (string) __('incoming_pdf.split.error.failed'));
        }
    }
}
