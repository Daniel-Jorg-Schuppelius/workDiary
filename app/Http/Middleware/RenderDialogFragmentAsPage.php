<?php
/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RenderDialogFragmentAsPage.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{Request, Response as HttpResponse};
use Illuminate\Support\HtmlString;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dialog-Fragmente (`…._*dialog*`-Views) sind für den Dialog-Host gebaut und
 * haben weder Layout noch CSS. Navigiert der Browser direkt dorthin — neuer
 * Tab, Strg-Klick, Lesezeichen, „In neuem Tab öffnen" —, käme nacktes HTML.
 * Dann bettet diese Middleware das Fragment in eine Seite.
 *
 * Erkannt wird die Navigation an den Fetch-Metadaten des Browsers; Tests
 * schicken keine, Dialog-fetches kommen als ajax() mit `?dialog=1`.
 */
class RenderDialogFragmentAsPage {
    private const FRAGMENT_VIEW = '/(^|[.\/])_[\w-]*dialog[\w-]*$/';

    public function handle(Request $request, Closure $next): Response {
        $response = $next($request);

        if (! $request->isMethod('GET')
            || ! $this->isNavigation($request)
            || $request->ajax()
            || $request->boolean('dialog')
            || $request->user() === null
            || ! $response instanceof HttpResponse
            || ! $response->isSuccessful()) {
            return $response;
        }

        $view = $response->getOriginalContent();
        if (! $view instanceof View || preg_match(self::FRAGMENT_VIEW, $view->name()) !== 1) {
            return $response;
        }

        return $response->setContent(view('layouts.dialog-page', [
            'fragment' => new HtmlString((string) $response->getContent()),
        ])->render());
    }

    private function isNavigation(Request $request): bool {
        $destination = $request->headers->get('Sec-Fetch-Dest');

        // Der Service Worker (public/sw.js) reicht Navigationen per fetch weiter:
        // dann „empty", aber mit dem Accept-Header der Navigation.
        return $destination === 'document'
            || ($destination === 'empty' && str_starts_with((string) $request->headers->get('Accept'), 'text/html'));
    }
}
