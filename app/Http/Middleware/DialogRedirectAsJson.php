<?php
/*
 * Created on   : Mon Sep 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DialogRedirectAsJson.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\{JsonResponse, RedirectResponse, Request};
use Illuminate\Support\ViewErrorBag;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dialog-Formulare (app.js, Header X-Entry-Dialog) senden per fetch. Folgt
 * fetch einer Weiterleitung, verbraucht deren GET den Flash, und das folgende
 * reload() zeigt nichts: Fehlermeldungen, Erfolgsmeldungen, sogar der nur
 * einmal sichtbare Webhook-Schlüssel gingen verloren (UI-Fuzz 2026-09-21).
 *
 * Validierungsfehler aus back()->withErrors() werden zu 422 (Toast im Dialog),
 * eigene Weiterleitungen zu {redirect}: der Dialog navigiert selbst dorthin,
 * und der Flash erscheint auf der Zielseite. Externe Ziele bleiben unverändert.
 */
class DialogRedirectAsJson {
    public function handle(Request $request, Closure $next): Response {
        $response = $next($request);

        if (! $request->headers->has('X-Entry-Dialog') || ! $response instanceof RedirectResponse) {
            return $response;
        }

        $session = $request->hasSession() ? $request->session() : null;
        $errors = $session?->get('errors');
        if ($errors instanceof ViewErrorBag && $errors->any()) {
            $session->forget(['errors', '_old_input']);

            return new JsonResponse(['message' => $errors->first(), 'errors' => $errors->getBag('default')->toArray()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $target = $response->getTargetUrl();
        $host = parse_url($target, PHP_URL_HOST);
        if (is_string($host) && $host !== $request->getHost()) {
            return $response;
        }

        return new JsonResponse(['redirect' => $target]);
    }
}
