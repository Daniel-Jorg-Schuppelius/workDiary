<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RendersLtiAutoPost.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Learning\Concerns;

use Illuminate\Http\Response;

/**
 * Selbst absendendes Formular im LTI-Ablauf (Feature 149) — ID-Token an ein Tool,
 * Deep-Linking-Antwort an eine Plattform, Weiterreichen eines fremden POST.
 * Die Antwort bringt eine eigene, enge CSP mit; die App-CSP erlaubte das Ziel nicht.
 */
trait RendersLtiAutoPost {
    /** @param  array<string, string>  $fields */
    private function autoPost(string $action, array $fields, string $formAction): Response {
        $nonce = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
        $response = response()->view('learning.lti.auto-post', ['action' => $action, 'fields' => $fields, 'nonce' => $nonce]);

        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'none'",
            "script-src 'nonce-{$nonce}'",
            "style-src 'unsafe-inline'",
            "form-action {$formAction}",
            "frame-ancestors 'none'",
            "base-uri 'none'",
        ]));
        $response->headers->set('Cache-Control', 'no-store');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
