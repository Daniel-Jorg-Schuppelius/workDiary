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

use App\Http\Controllers\Controller;
use App\Models\{Invoice, User};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class InvoiceController extends Controller {
    public function index(): View {
        /** @var User $user */
        $user = Auth::guard('customer')->user();

        $invoices = Invoice::query()
            ->where('customer_id', $user->customer_id)
            ->orderByDesc('issued_on')
            ->orderByDesc('id')
            ->paginate(25);

        return view('customer.invoices.index', ['invoices' => $invoices]);
    }
}
