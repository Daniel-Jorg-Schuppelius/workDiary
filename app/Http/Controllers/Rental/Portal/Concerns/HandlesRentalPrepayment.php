<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HandlesRentalPrepayment.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Rental\Portal\Concerns;

use App\Enums\Rental\RentalRequestStatus;
use App\Models\Platform\User;
use App\Models\Rental\RentalRequest;
use App\Services\Invoicing\OnlinePayment\Exceptions\OnlinePaymentException;
use App\Services\Invoicing\OnlinePayment\OnlinePaymentService;
use App\Services\Rental\RentalRequestService;
use App\Support\{CarbonFmt, ErrorText, Sqid};
use Illuminate\Http\{RedirectResponse, Request};
use RuntimeException;

/**
 * Online-Vorauszahlung der Direktbuchung im Portal (MVP-1515): Weiterleitung
 * zum Zahlungsanbieter und Auswertung der Rückkehr — für Geräte und Wohnungen.
 */
trait HandlesRentalPrepayment {
    /** Ist der Anbieter nicht erreichbar, hält die Reservierung bis zur Frist; „Jetzt bezahlen“ versucht es erneut. */
    protected function prepaymentCheckout(RentalRequest $booking, string $fallbackRoute): RedirectResponse {
        try {
            return redirect()->away(app(RentalRequestService::class)->prepaymentCheckoutUrl($booking));
        } catch (OnlinePaymentException) {
            return redirect()->route($fallbackRoute)->with('error', __('rental.prepayment.checkout_failed', [
                'until' => $booking->payment_due_at !== null ? CarbonFmt::ftime($booking->payment_due_at) : '—',
            ]));
        } catch (RuntimeException $e) {
            return redirect()->route($fallbackRoute)->with('error', ErrorText::for($e));
        }
    }

    /** Rückkehr vom Anbieter (`?zahlung=<Kennung>`): Stand nachfragen statt auf den Webhook zu warten. */
    protected function settlePrepaymentReturn(Request $request, User $user): void {
        $sqid = $request->query('zahlung');
        if (! is_string($sqid) || $sqid === '') {
            return;
        }
        $booking = RentalRequest::query()->withoutGlobalScopes()
            ->where('organization_id', $user->organization_id)
            ->where('customer_id', $user->customer_id)
            ->whereKey(Sqid::decode(RentalRequest::class, $sqid) ?? 0)
            ->first();
        if (! $booking instanceof RentalRequest || ! in_array($booking->status, [RentalRequestStatus::AwaitingPayment, RentalRequestStatus::Accepted], true)) {
            return;
        }

        if ($booking->isAwaitingPayment()) {
            app(OnlinePaymentService::class)->refreshOpenFor($booking);
            $booking->refresh();
        }

        $confirmed = $booking->status === RentalRequestStatus::Accepted;
        session()->now($confirmed ? 'success' : 'warning', $confirmed ? __('rental.prepayment.confirmed') : __('rental.prepayment.pending'));
    }
}
