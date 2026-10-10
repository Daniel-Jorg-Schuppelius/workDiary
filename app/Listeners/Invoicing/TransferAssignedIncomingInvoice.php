<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TransferAssignedIncomingInvoice.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Listeners\Invoicing;

use App\Events\Invoicing\IncomingInvoiceAssigned;
use App\Jobs\Invoicing\TransferIncomingInvoiceJob;
use App\Listeners\ModuleListener;
use App\Services\Invoicing\EInvoice\IncomingInvoiceTransferService;

/** Nach der Zuordnung geht der Eingang an die Buchhaltung (Feature 163, MVP-1111, Entscheid E1). */
final class TransferAssignedIncomingInvoice extends ModuleListener {
    public function __construct(private readonly IncomingInvoiceTransferService $transfers) {}

    protected function module(): string {
        return 'invoicing';
    }

    public function handle(IncomingInvoiceAssigned $event): void {
        $organization = $event->incoming->organization;
        if ($organization === null || ! $this->shouldHandle($organization) || ! $this->transfers->hasTargets($organization)) {
            return;
        }

        TransferIncomingInvoiceJob::dispatch((int) $event->incoming->id);
    }
}
