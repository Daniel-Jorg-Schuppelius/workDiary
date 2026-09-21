<?php
/*
 * Created on   : Mon Sep 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ModuleKeyMissingException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\{JsonResponse, Request};
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Modul mit eigenem Schlüssel (Hinweisgeber, Datenschutz) ohne verwendbaren
 * Schlüssel: Betriebsfehler, kein Programmfehler. Öffentliche Portale zeigten
 * einen rohen 500 (UI-Fuzz 2026-09-21) — jetzt 503 mit Hinweis; gemeldet wird
 * weiterhin, damit der Betreiber es im Log sieht.
 */
class ModuleKeyMissingException extends RuntimeException {
    public function render(Request $request): Response {
        $message = (string) __('Dieser Bereich ist noch nicht eingerichtet. Bitte wenden Sie sich an den Betreiber.');

        if ($request->expectsJson()) {
            return new JsonResponse(['message' => $message], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return response()->view('errors.module-unavailable', ['userMessage' => $message], Response::HTTP_SERVICE_UNAVAILABLE);
    }
}
