<?php
/*
 * Created on   : Tue Oct 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrderConfirmationController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Enums\Numbering\NumberScope;
use App\Enums\Sales\OrderConfirmationStatus;
use App\Http\Controllers\Controller;
use App\Models\Platform\User;
use App\Models\Sales\{OrderConfirmation, Quote};
use App\Services\Invoicing\OrderConfirmationPdfRenderer;
use App\Services\Numbering\{NumberAuthority, NumberSequenceService};
use App\Services\Sales\OrderConfirmationService;
use App\Support\ErrorText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/** Auftragsbestätigung als eigener Beleg (Phase 145, MVP-1162). */
class OrderConfirmationController extends Controller {
    public function __construct(private readonly OrderConfirmationService $confirmations) {}

    /** Aus dem angenommenen Angebot anlegen; eine bestehende AB wird geöffnet. */
    public function store(Quote $quote): RedirectResponse {
        Gate::authorize('convert', $quote);
        try {
            $confirmation = $this->confirmations->fromQuote($quote, $this->actor());
        } catch (RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return redirect()->route('order-confirmations.show', $confirmation);
    }

    public function show(OrderConfirmation $orderConfirmation): View {
        Gate::authorize('view', $orderConfirmation);
        $orderConfirmation->load(['items', 'customer', 'quote']);
        $authority = app(NumberAuthority::class);

        return view('order-confirmations.show', [
            'confirmation' => $orderConfirmation,
            // Entwurfsnummer: Das Programm vergibt die Nummer beim Bestätigen (MVP-1164).
            'numberIssuer' => NumberSequenceService::isDraft($orderConfirmation->number)
                ? $authority->sourceLabel($authority->sourceFor((int) $orderConfirmation->organization_id, NumberScope::OrderConfirmation))
                : null,
        ]);
    }

    public function confirm(OrderConfirmation $orderConfirmation): RedirectResponse {
        Gate::authorize('confirm', $orderConfirmation);
        try {
            $this->confirmations->confirm($orderConfirmation, $this->actor());
        } catch (RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return redirect()->route('order-confirmations.show', $orderConfirmation)
            ->with('status', __('quotes.order_confirmation.flash.confirmed', ['number' => $orderConfirmation->refresh()->number]));
    }

    public function cancel(OrderConfirmation $orderConfirmation): RedirectResponse {
        Gate::authorize('cancel', $orderConfirmation);
        $this->confirmations->cancel($orderConfirmation, $this->actor());

        return redirect()->route('order-confirmations.show', $orderConfirmation)
            ->with('status', __('quotes.order_confirmation.flash.cancelled'));
    }

    public function pdf(OrderConfirmation $orderConfirmation, OrderConfirmationPdfRenderer $renderer): Response {
        Gate::authorize('view', $orderConfirmation);
        abort_if($orderConfirmation->status === OrderConfirmationStatus::Draft, 409, (string) __('quotes.order_confirmation.error.not_confirmed'));

        return response($renderer->output($orderConfirmation), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('attachment; filename="auftragsbestaetigung-%s.pdf"', preg_replace('/[^A-Za-z0-9._-]/', '_', $orderConfirmation->number)),
        ]);
    }

    private function actor(): User {
        $user = Auth::user();

        return $user instanceof User ? $user : abort(401);
    }
}
