<?php
/*
 * Created on   : Tue Oct 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceNode.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\Relations;

use App\Models\Invoicing\Invoice;
use App\Services\Billing\Dto\{DocumentNode, DocumentRef};
use Illuminate\Support\Facades\Gate;

/** Rechnung als Knoten im Belegverbund (MVP-1157); auch Angebot und Fertigung beschreiben ihre Rechnungen so. */
final class InvoiceNode {
    public static function of(Invoice $invoice): DocumentNode {
        return new DocumentNode(
            ref: DocumentRef::forModel($invoice),
            label: $invoice->documentLabel(),
            number: $invoice->number,
            date: $invoice->issued_on ?? $invoice->created_at,
            state: $invoice->status->label(),
            url: Gate::allows('view', $invoice) ? route('invoices.show', $invoice) : null,
        );
    }
}
