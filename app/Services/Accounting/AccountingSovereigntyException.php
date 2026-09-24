<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingSovereigntyException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\Finance\AccountingSovereignty;
use Carbon\CarbonInterface;
use Illuminate\Http\{JsonResponse, Request};
use Symfony\Component\HttpFoundation\Response;

/**
 * Für diesen Zeitraum darf lokal nicht festgeschrieben werden (Feature 125,
 * MVP-671). Das Gegenstück zur {@see \App\Services\Billing\BillingModeLockedException}
 * auf der Buchungsachse.
 */
class AccountingSovereigntyException extends \RuntimeException {
    public function __construct(
        public readonly AccountingSovereignty $sovereignty,
        public readonly CarbonInterface $date,
        ?string $provider = null,
    ) {
        parent::__construct((string) __('accounting.ledger.error.sovereignty', [
            'date' => $date->format(\App\Support\Formats::date()),
            'holder' => $provider !== null && $provider !== '' ? $provider : $sovereignty->label(),
        ]));
    }

    /** Fachliche Sperre, kein Serverfehler: Meldung statt 500 (Dialoge per JSON). */
    public function render(Request $request): Response {
        if ($request->expectsJson()) {
            return new JsonResponse(['message' => $this->getMessage()], Response::HTTP_CONFLICT);
        }

        return back()->withInput()->with('error', $this->getMessage());
    }
}
