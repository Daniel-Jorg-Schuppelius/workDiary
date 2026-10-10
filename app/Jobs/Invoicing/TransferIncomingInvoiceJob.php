<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TransferIncomingInvoiceJob.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Jobs\Invoicing;

use App\Models\Invoicing\IncomingEInvoice;
use App\Services\Invoicing\EInvoice\IncomingInvoiceTransferService;
use App\Support\OrganizationContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\{ShouldBeUniqueUntilProcessing, ShouldQueue};
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};

/** Übergibt einen zugeordneten Rechnungseingang an die Buchhaltungsziele (Feature 163, MVP-1111). */
final class TransferIncomingInvoiceJob implements ShouldBeUniqueUntilProcessing, ShouldQueue {
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $incomingId) {
        $this->afterCommit();
    }

    public function uniqueId(): string {
        return (string) $this->incomingId;
    }

    public function handle(IncomingInvoiceTransferService $transfers): void {
        $incoming = IncomingEInvoice::query()->withoutGlobalScopes()->find($this->incomingId);
        if (! $incoming instanceof IncomingEInvoice || $incoming->organization === null) {
            return;
        }

        // Fehler je Ziel landen im Übergabejournal; der Job selbst scheitert nicht.
        OrganizationContext::run($incoming->organization, static fn (): array => $transfers->transfer($incoming));
    }
}
