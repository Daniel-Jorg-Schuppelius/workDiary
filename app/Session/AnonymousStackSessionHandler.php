<?php
/*
 * Created on   : Sun Sep 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AnonymousStackSessionHandler.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Session;

use Illuminate\Session\DatabaseSessionHandler;

/**
 * Sitzungsablage, die auf den anonymen Portalen KEINE Herkunftsdaten schreibt.
 *
 * Der Standard-Handler legt zu jeder Sitzung Adresse und Browserkennung in die
 * `sessions`-Tabelle. Auf dem Hinweisgeber-Meldeportal steht in derselben Zeile
 * die Sitzung, über die der Fall geführt wird — Adresse und Fall liegen damit
 * nebeneinander, und genau diese Verknüpfung soll es dort nicht geben
 * (Sicherheitsaudit 2026-09-13). Für das Betroffenen- und das Karriereportal
 * gilt dasselbe: Wer sich dort meldet, hinterlässt sonst eine Spur, die er
 * nicht hinterlassen wollte.
 *
 * Entschieden wird über die Middleware-Gruppe der Route, nicht über den Pfad:
 * Das Betroffenenportal liegt auf einem frei wählbaren Slug, ein Pfadvergleich
 * ginge dort ins Leere.
 *
 * Die Drosseln dieser Portale arbeiten ohnehin mit einem Abdruck der Adresse
 * statt mit der Adresse selbst — hier bleibt sie ganz weg.
 */
final class AnonymousStackSessionHandler extends DatabaseSessionHandler {
    /** Middleware-Gruppen ohne Herkunftsdaten in der Sitzung. */
    private const ANONYMOUS_GROUPS = ['whistleblowing', 'dsar', 'careers'];

    /**
     * @param  array<mixed>  $payload
     * @return $this
     */
    protected function addRequestInformation(&$payload) {
        if ($this->onAnonymousStack()) {
            $payload = array_merge($payload, ['ip_address' => null, 'user_agent' => null]);

            return $this;
        }

        return parent::addRequestInformation($payload);
    }

    /**
     * Keine Kontobindung in der Sitzungszeile der anonymen Portale: `user_id`
     * käme aus dem Standard-Guard und zeigte auf den angemeldeten Mitarbeiter
     * (Sicherheitsaudit 2026-09-17, privacy-wb-1). Zusätzlich zur Cookie-Trennung
     * ({@see \App\Http\Middleware\UseAnonymousPortalSession}) — doppelt hält.
     *
     * @param  array<mixed>  $payload
     * @return $this
     */
    protected function addUserInformation(&$payload) {
        if ($this->onAnonymousStack()) {
            $payload = array_merge($payload, ['user_id' => null]);

            return $this;
        }

        return parent::addUserInformation($payload);
    }

    private function onAnonymousStack(): bool {
        $container = $this->container;
        if ($container === null || ! $container->bound('request')) {
            return false;
        }

        $route = $container->make('request')->route();
        if ($route === null) {
            return false;
        }

        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && in_array($middleware, self::ANONYMOUS_GROUPS, true)) {
                return true;
            }
        }

        return false;
    }
}
