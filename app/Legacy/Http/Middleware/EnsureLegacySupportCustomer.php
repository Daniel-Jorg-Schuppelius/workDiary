<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EnsureLegacySupportCustomer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Legacy\Http\Middleware;

use App\Legacy\Models\LegacySupportCustomer;
use Closure;
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kundensitzung des Support-Portals. Eine Anmeldung im neuen System zählt hier
 * nicht: das Portal zeigt nur die Aufträge des angemeldeten Kunden.
 */
class EnsureLegacySupportCustomer {
    public const string SESSION_KEY = 'legacy_support_customer';

    /** Fingerabdruck des Passworts bei der Anmeldung; ein neues Passwort beendet andere Sitzungen. */
    public const string SESSION_FINGERPRINT = 'legacy_support_customer_fp';

    public const string REQUEST_ATTRIBUTE = 'legacySupportCustomer';

    public static function fingerprint(LegacySupportCustomer $customer): string {
        return CryptoHelper::hmac((string) $customer->passw, (string) config('app.key'));
    }

    public function handle(Request $request, Closure $next): Response {
        $session = $request->session();
        $customerId = (int) $session->get(self::SESSION_KEY, 0);

        try {
            $customer = $customerId >= LegacySupportCustomer::FIRST_CUSTOMER_ID
                ? LegacySupportCustomer::query()->find($customerId)
                : null;
        } catch (\Throwable) {
            return redirect()->route('legacy.portal.login')
                ->withErrors(['username' => __('legacy_support.portal.unavailable')]);
        }

        if (! $customer instanceof LegacySupportCustomer
            || ! hash_equals((string) $session->get(self::SESSION_FINGERPRINT, ''), self::fingerprint($customer))) {
            $session->forget([self::SESSION_KEY, self::SESSION_FINGERPRINT]);

            return redirect()->route('legacy.portal.login');
        }

        $request->attributes->set(self::REQUEST_ATTRIBUTE, $customer);

        return $next($request);
    }
}
