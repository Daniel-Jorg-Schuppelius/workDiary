<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ImportIncomingProviderInvoiceJob.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Jobs\Reselling;

use App\Models\Invoicing\IncomingEInvoice;
use App\Models\Platform\User;
use App\Services\Reselling\Register\IncomingProviderInvoiceImport;
use App\Support\OrganizationContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\{ShouldBeUniqueUntilProcessing, ShouldQueue};
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\Log;

/**
 * Eingangsrechnung eines Anbieter-Lieferanten als Einkauf übernehmen
 * (Feature 185, MVP-1420). Befund und Ergebnis stehen im Log; der Einkauf-
 * Reiter zeigt die Zeilen samt Herkunft.
 */
final class ImportIncomingProviderInvoiceJob implements ShouldBeUniqueUntilProcessing, ShouldQueue {
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $incomingId, public readonly ?int $userId = null) {
        $this->afterCommit();
    }

    public function uniqueId(): string {
        return (string) $this->incomingId;
    }

    public function handle(IncomingProviderInvoiceImport $import): void {
        $incoming = IncomingEInvoice::query()->withoutGlobalScopes()->find($this->incomingId);
        if (! $incoming instanceof IncomingEInvoice || $incoming->organization === null) {
            return;
        }
        $user = $this->userId !== null ? User::query()->find($this->userId) : null;
        $result = OrganizationContext::run($incoming->organization, static fn(): ?array => $import->import($incoming, $user instanceof User ? $user : null));
        if ($result === null) {
            return;
        }
        Log::info('resale: Eingangsrechnung als Einkauf übernommen', ['incoming_id' => $incoming->id, 'organization_id' => $incoming->organization_id] + $result);
    }
}
