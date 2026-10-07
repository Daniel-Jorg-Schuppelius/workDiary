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
 *
 * Bleibt der Dialog offen — Folgedialog (Rückkehr in die Ursprungsmaske) oder
 * Weiterleitung auf das eigene Fragment —, gäbe es keine Zielseite: dann
 * {redirect, stay, messages}, und die Meldungen verlassen die Sitzung.
 */
class DialogRedirectAsJson {
    /** Flash-Schlüssel des Layouts → Ton des Toasts. */
    private const FLASH_TONES = ['success' => 'success', 'status' => 'success', 'error' => 'error', 'warning' => 'warning', 'info' => 'info'];

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

        if (! $this->staysInDialog($request, $target)) {
            return new JsonResponse(['redirect' => $target]);
        }

        $messages = [];
        foreach (self::FLASH_TONES as $key => $tone) {
            if ($session?->has($key)) {
                $messages[] = ['tone' => $tone, 'message' => (string) $session->pull($key)];
            }
        }

        return new JsonResponse(['redirect' => $target, 'stay' => true, 'messages' => $messages]);
    }

    private function staysInDialog(Request $request, string $target): bool {
        if ($request->headers->get('X-Entry-Dialog-Stacked') === '1') {
            return true;
        }

        $dialog = (string) $request->headers->get('X-Entry-Dialog-Url', '');

        return $dialog !== '' && parse_url($dialog, PHP_URL_PATH) === parse_url($target, PHP_URL_PATH);
    }
}
