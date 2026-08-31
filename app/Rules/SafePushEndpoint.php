<?php
/*
 * Created on   : Sun Aug 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SafePushEndpoint.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Rules;

use App\Support\UrlSafety;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Push-Endpunkt eines Browsers (Sicherheitsscan 2026-08-23, S-48).
 *
 * Der Wert kommt vom Client und der Server ruft ihn später selbst auf — ohne
 * Prüfung wäre das eine blinde SSRF: ein Angreifer trägt `http://127.0.0.1:…`
 * ein und lässt den Server bei jeder Benachrichtigung dorthin POSTen.
 *
 * Verlangt wird https (die Push-Dienste sprechen ausschließlich TLS) und eine
 * öffentlich erreichbare Adresse. Bewusst **keine** Anbieter-Positivliste:
 * Push-Dienste kommen und gehen, und eine veraltende Liste sperrt irgendwann
 * gültige Browser aus, statt Angreifer.
 */
class SafePushEndpoint implements ValidationRule {
    public function validate(string $attribute, mixed $value, Closure $fail): void {
        $url = is_string($value) ? trim($value) : '';

        if (! str_starts_with(mb_strtolower($url), 'https://')) {
            $fail(__('validation.push_endpoint_scheme'))->translate();

            return;
        }

        if (! UrlSafety::isAcceptableExternalHttpUrl($url)) {
            $fail(__('validation.push_endpoint_unreachable'))->translate();
        }
    }
}
