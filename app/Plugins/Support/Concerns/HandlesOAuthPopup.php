<?php
/*
 * Created on   : Fri Aug 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HandlesOAuthPopup.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\Concerns;

use Illuminate\Http\{RedirectResponse, Request};
use Symfony\Component\HttpFoundation\Response;

/**
 * Popup-fähiger OAuth-Connect-Flow — gemeinsam für ConnectionOAuthController,
 * IntakeOAuthController und BackupTargetOAuthController. Statt eines
 * ganzseitigen Redirects kann das Admin-Panel den Flow in einem Popup-Fenster
 * öffnen; die Callback-Seite meldet das Ergebnis per postMessage zurück und
 * schließt sich selbst (Frontend: resources/js/oauth-popup.js).
 *
 * Der Popup-Wunsch reist als Flag im einmalig einlösbaren OAuth-State-Payload
 * mit, damit der Provider-Callback ohne eigenen Query-Parameter auskommt. Die
 * eigentliche Rückmeldung bleibt der Session-Flash: das Opener-Fenster lädt die
 * Übersicht neu und zeigt ihn dort an — ein Weg für Popup und Ganzseite.
 */
trait HandlesOAuthPopup {
    /** Wurde der Flow aus einem Popup gestartet? (Frontend hängt `popup=1` an.) */
    protected function oauthPopupRequested(Request $request): bool {
        return $request->boolean('popup');
    }

    /**
     * Popup-Flag aus dem eingelösten State-Payload.
     *
     * @param  array<string, mixed>|null  $payload
     */
    protected function oauthPayloadIsPopup(?array $payload): bool {
        return $payload !== null && (bool) ($payload['popup'] ?? false);
    }

    /**
     * Ergebnis ausliefern: im Popup die Abschlussseite (postMessage + Close),
     * sonst der übergebene Redirect. Der Flash ist bereits über `->with()` in
     * die Session geschrieben und erscheint beim Neuladen der Übersicht.
     */
    protected function respondToOAuth(bool $isPopup, bool $success, RedirectResponse $redirect): Response {
        if (! $isPopup) {
            return $redirect;
        }

        return response()->view('plugins.oauth-popup-result', ['success' => $success]);
    }
}
