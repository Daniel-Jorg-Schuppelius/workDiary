<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ConfirmPrepaidRentalBooking.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Listeners\Rental;

use App\Enums\Invoicing\OnlinePaymentStatus;
use App\Events\Invoicing\OnlinePrepaymentStatusChanged;
use App\Listeners\ModuleListener;
use App\Models\Rental\RentalRequest;
use App\Services\Rental\RentalRequestService;
use App\Support\MorphMap;

/**
 * Bezahlte Vorauszahlung einer Direktbuchung bestätigt die Buchung (MVP-1515).
 */
final class ConfirmPrepaidRentalBooking extends ModuleListener {
    public function __construct(private readonly RentalRequestService $requests) {}

    protected function module(): string {
        return 'rental';
    }

    public function handle(OnlinePrepaymentStatusChanged $event): void {
        $payment = $event->payment;
        if ($payment->status !== OnlinePaymentStatus::Paid
            || $payment->subject_type === null
            || ! MorphMap::is($payment->subject_type, RentalRequest::class)
            || ! $this->shouldHandle((int) $payment->organization_id)) {
            return;
        }

        $request = RentalRequest::query()->withoutGlobalScopes()
            ->where('organization_id', $payment->organization_id)
            ->find($payment->subject_id);
        if ($request instanceof RentalRequest) {
            $this->requests->confirmPrepaid($request, $payment);
        }
    }
}
