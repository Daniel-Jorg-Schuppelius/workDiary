<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoicePeppolController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\Peppol\PeppolInvoiceDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

/**
 * Rechnungsversand über Peppol (Feature 066, MVP-734).
 *
 * Ein eigener Weg neben dem Mailversand — nicht dessen Variante: Peppol
 * adressiert Teilnehmerkennungen statt Postfächer, und der Zugangsnachweis ist
 * die Transportquittung des Access Points, nicht eine Message-ID des Mailers.
 * Beide schreiben aber dasselbe Dispatch-Log.
 */
class InvoicePeppolController extends Controller {
    public function send(Invoice $invoice, PeppolInvoiceDispatcher $dispatcher): RedirectResponse {
        Gate::authorize('send', $invoice);

        try {
            $dispatch = $dispatcher->send($invoice);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $meta = $dispatch->meta ?? [];

        return back()->with('status', __('peppol.flash.sent', [
            'participant' => (string) $dispatch->recipient,
            'message' => (string) ($meta['message_id'] ?? '—'),
            'status' => (string) ($meta['transport_status'] ?? $dispatch->status),
        ]));
    }
}
