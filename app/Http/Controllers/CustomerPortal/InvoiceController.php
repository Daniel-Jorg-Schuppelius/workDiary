<?php
/*
 * Created on   : Sat May 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\CustomerPortal;

use App\Enums\Invoicing\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\User;
use App\Services\Invoicing\OnlinePayment\InvoicePaymentLinkService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class InvoiceController extends Controller {
    public function index(): View {
        /** @var User $user */
        $user = Auth::guard('customer')->user();

        // Entwürfe sind interne Arbeitsstände (MVP-1019) — der Kunde sieht nur Ausgestelltes.
        $invoices = Invoice::query()
            ->where('customer_id', $user->customer_id)
            ->where('status', '!=', InvoiceStatus::Draft)
            ->orderByDesc('issued_on')
            ->orderByDesc('id')
            ->paginate(25);

        // Online-Zahlung (MVP-1067): im Portal unabhängig vom Schalter für PDF und Mail.
        $links = app(InvoicePaymentLinkService::class);
        $payLinks = $invoices->getCollection()
            ->mapWithKeys(static fn (Invoice $invoice): array => [$invoice->id => $links->urlFor($invoice, onDocuments: false)])
            ->filter();

        return view('customer.invoices.index', ['invoices' => $invoices, 'payLinks' => $payLinks]);
    }
}
