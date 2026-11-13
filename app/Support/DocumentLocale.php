<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentLocale.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Support;

use App\Models\Customer\Customer;
use App\Models\Platform\Organization;
use Carbon\Carbon;
use Closure;
use Illuminate\Support\Facades\App;

/**
 * Belegsprache (Feature 034, MVP-721; Vollscan H19): Kunde → Organisation →
 * aktive Anzeige-Sprache. Reine Darstellungsregel für Rechnung/Angebot/AB/
 * Mahnung/Lieferschein und den Belegversand — Hash-Ketten und tax_context
 * kennen keine Sprache. Eine ausgestellte Rechnung führt ihre Sprache im
 * Partei-Snapshot ({@see \App\Models\Invoicing\Invoice::documentLocale()}).
 */
final class DocumentLocale {
    public static function for(?Customer $customer, ?Organization $organization = null): string {
        $candidate = $customer?->document_locale;
        if (is_string($candidate) && Locales::isSupported($candidate)) {
            return $candidate;
        }

        $organization ??= $customer?->organization;
        $orgLocale = $organization?->locale;
        if (is_string($orgLocale) && Locales::isSupported($orgLocale)) {
            return $orgLocale;
        }

        return App::getLocale();
    }

    /**
     * Führt den Callback in der Belegsprache aus — App- UND Carbon-Locale
     * (Laravels withLocale lässt Carbon aus, `->fdate()` würde sonst mit
     * deutschen Monatsnamen in eine französische Rechnung schreiben).
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function within(?Customer $customer, ?Organization $organization, Closure $callback): mixed {
        return self::run(self::for($customer, $organization), $callback);
    }

    /**
     * Führt den Callback in einer festen Belegsprache aus, etwa der beim
     * Ausstellen eingefrorenen einer Rechnung (MVP-1680).
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function run(string $locale, Closure $callback): mixed {
        $previousApp = App::getLocale();
        $previousCarbon = Carbon::getLocale();

        App::setLocale($locale);
        Carbon::setLocale(Locales::carbon($locale));
        try {
            return $callback();
        } finally {
            App::setLocale($previousApp);
            Carbon::setLocale($previousCarbon);
        }
    }
}
