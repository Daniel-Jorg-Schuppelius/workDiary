<?php
/*
 * Created on   : Sun Aug 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SetsTransportSecurity.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Middleware\Concerns;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * HSTS für die öffentlichen Stacks (Sicherheitsscan 2026-08-23, S-62).
 *
 * Hinweisgeberportal, Karrierebereich und B2B-Katalog haben eigene
 * Header-Middlewares und setzten CSP, X-Frame-Options und Referrer-Policy —
 * aber kein `Strict-Transport-Security`. Das lag nur im web-/api-Stack.
 *
 * Beim **Hinweisgebersystem** wiegt das am schwersten: wer zum ersten Mal über
 * `http://` kommt und noch keinen Pin hat, überträgt seine Meldung im Klartext
 * — und Anonymität ist dort der ganze Zweck.
 *
 * Ein gemeinsamer Trait statt vier Kopien: Header-Werte, die man an mehreren
 * Stellen pflegt, laufen auseinander.
 */
trait SetsTransportSecurity {
    protected function applyTransportSecurity(Request $request, Response $response): void {
        // Nur über HTTPS — sonst bricht die lokale Entwicklung über http.
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
    }
}
