<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QuoteExternalInvoiceController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Enums\Invoicing\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\Invoicing\Invoice;
use App\Models\Sales\Quote;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Angenommenes Angebot als „im Buchhaltungsprogramm abgerechnet“ markieren
 * (MVP-1124): Es verlässt die Belegkette „Angenommene Angebote ohne
 * Rechnung“. Recht wie „In Rechnung überführen“; wer markiert, hält das
 * Audit-Log fest.
 */
class QuoteExternalInvoiceController extends Controller {
    public function form(Quote $quote): View {
        $this->authorizeMarking($quote);

        return view('quotes._external_invoice_dialog', ['quote' => $quote]);
    }

    public function store(Request $request, Quote $quote): RedirectResponse {
        $this->authorizeMarking($quote);
        $data = $request->validate([
            'external_invoice_number' => ['nullable', 'string', 'max:64'],
        ]);

        $quote->forceFill([
            'invoiced_externally_at' => now(),
            'external_invoice_number' => ($data['external_invoice_number'] ?? null) ?: null,
        ])->save();

        return back()->with('status', __('quotes.external_invoice.marked'));
    }

    public function destroy(Quote $quote): RedirectResponse {
        Gate::authorize('convert', $quote);

        $quote->forceFill(['invoiced_externally_at' => null, 'external_invoice_number' => null])->save();

        return back()->with('status', __('quotes.external_invoice.unmarked'));
    }

    /** Nur angenommen und ohne lokale Rechnung — sonst führt die Rechnung den Fall. */
    private function authorizeMarking(Quote $quote): void {
        Gate::authorize('convert', $quote);
        abort_if(Invoice::query()->where('quote_id', $quote->id)->where('status', '!=', InvoiceStatus::Cancelled)->exists(), 409);
    }
}
