<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingInvoiceAssigned.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Events\Invoicing;

use App\Models\Invoicing\IncomingEInvoice;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** Ein Rechnungseingang hat seine Gegenpartei (Feature 163, MVP-1108); danach folgt die Übergabe. Nach Commit. */
final class IncomingInvoiceAssigned implements ShouldDispatchAfterCommit {
    use Dispatchable;

    public function __construct(public readonly IncomingEInvoice $incoming) {}
}
