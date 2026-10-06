<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OnlinePaymentController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Invoicing;

use App\Enums\Invoicing\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\Organization;
use App\Services\Invoicing\OnlinePayment\Exceptions\OnlinePaymentException;
use App\Services\Invoicing\OnlinePayment\{InvoicePaymentLinkService, OnlinePaymentService};
use App\Support\{DocumentLocale, OrganizationContext};
use Illuminate\Http\{RedirectResponse, Request, Response};

/**
 * Öffentlicher Zahlungslink und Webhook der Online-Zahlung (MVP-1067),
 * sitzungslos (`routes/payments.php`). Der Link führt zur Bezahlseite des
 * Anbieters über den offenen Betrag; die Rückkehr fragt den Stand nach.
 */
class OnlinePaymentController extends Controller {
    public function show(string $token, InvoicePaymentLinkService $links, OnlinePaymentService $payments): Response|RedirectResponse {
        [$invoice, $organization] = $this->invoice($token, $links);

        return OrganizationContext::run($organization, function () use ($invoice, $token, $payments): Response|RedirectResponse {
            try {
                return redirect()->away($payments->checkoutUrl($invoice, $token));
            } catch (OnlinePaymentException $e) {
                return $this->page($invoice, $e->reason);
            }
        });
    }

    public function done(string $token, InvoicePaymentLinkService $links, OnlinePaymentService $payments): Response {
        [$invoice, $organization] = $this->invoice($token, $links);

        return OrganizationContext::run($organization, function () use ($invoice, $payments): Response {
            $payments->refreshOpen($invoice);

            return $this->page($invoice->refresh(), $invoice->status === InvoiceStatus::Paid ? OnlinePaymentException::PAID : 'processing');
        });
    }

    public function webhook(string $provider, Request $request, OnlinePaymentService $payments): Response {
        $payments->handleWebhook($provider, $request);

        // Immer 200: unbekannte Zahlungen verraten nichts, der Anbieter wiederholt nicht endlos.
        return response()->noContent(200);
    }

    /** @return array{0: Invoice, 1: Organization} */
    private function invoice(string $token, InvoicePaymentLinkService $links): array {
        $invoice = $links->resolve($token);
        $organization = $invoice?->organization;
        abort_unless($invoice instanceof Invoice && $organization instanceof Organization, 404);

        return [$invoice, $organization];
    }

    private function page(Invoice $invoice, string $reason): Response {
        $invoice->loadMissing(['customer', 'organization']);
        app()->setLocale(DocumentLocale::for($invoice->customer, $invoice->organization));

        return response()->view('payments.status', [
            'invoice' => $invoice,
            'reason' => $reason,
        ], $reason === OnlinePaymentException::UNAVAILABLE ? 503 : 200);
    }
}
