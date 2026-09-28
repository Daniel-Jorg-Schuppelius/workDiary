<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BlockBannedIp.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Security\IpBanService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Temporäre IP-Sperre (MVP-450): gesperrte Adressen bekommen 429 — nur ohne
 * Anmeldung. Angemeldete Sitzungen und gültige API-Tokens laufen weiter
 * (CGNAT/Firmen-NAT: eine Sperre darf keine Kollegen aussperren).
 */
final class BlockBannedIp {
    public function __construct(private readonly IpBanService $bans) {}

    public function handle(Request $request, Closure $next): Response {
        if (! $this->bans->enabled() || $request->user() !== null || $request->user('sanctum') !== null) {
            return $next($request);
        }
        $until = $this->bans->bannedUntil((string) $request->ip());
        if ($until !== null) {
            abort(429, (string) __('Zu viele Fehlversuche von dieser Adresse. Bitte versuchen Sie es später erneut.'), ['Retry-After' => (string) max(1, $until->getTimestamp() - time())]);
        }

        return $next($request);
    }
}
