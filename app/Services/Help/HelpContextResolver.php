<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpContextResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Help;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;

/**
 * Löst den Hilfe-Kontext (Topic-Code) der aktuellen Seite über die
 * Route→Topic-Registry in config/help-topics.php auf (Feature 039).
 *
 * Matching wie bei {@see \App\Services\Navigation\NavGate}: exakter
 * Route-Name zuerst, danach Wildcard-Muster via Str::is in Config-Reihenfolge
 * (erster Treffer gewinnt).
 */
class HelpContextResolver {
    public function __construct(
        private readonly HelpTopicResolver $topics,
    ) {}

    /**
     * Topic-Code für die Route der Anfrage bzw. die übergebene Route —
     * null, wenn kein Registry-Eintrag passt (reines Pattern-Matching,
     * KEIN Sichtbarkeits-Check).
     */
    public function currentTopicFor(Request|Route $routeOrRequest): ?string {
        $route = $routeOrRequest instanceof Request ? $routeOrRequest->route() : $routeOrRequest;
        if (! $route instanceof Route) {
            return null;
        }

        $name = $route->getName();
        if ($name === null || $name === '') {
            return null;
        }

        /** @var array<string, mixed> $map */
        $map = (array) config('help-topics.routes', []);

        // Exakter Treffer hat Vorrang vor allen Wildcards.
        if (isset($map[$name]) && is_string($map[$name]) && $map[$name] !== '') {
            return $map[$name];
        }

        foreach ($map as $pattern => $topic) {
            if (! is_string($topic) || $topic === '') {
                continue;
            }
            if (Str::is($pattern, $name)) {
                return $topic;
            }
        }

        return null;
    }

    /**
     * Wie {@see currentTopicFor()}, liefert den Topic-Code aber nur, wenn das
     * Topic tatsächlich existiert UND für den Nutzer sichtbar ist
     * (audience-Filter + Locale-Fallback via HelpTopicResolver). Damit
     * erscheint im Layout nie ein "toter" Hilfe-Button.
     */
    public function visibleTopicFor(Request|Route $routeOrRequest, ?User $user): ?string {
        $topic = $this->currentTopicFor($routeOrRequest);
        if ($topic === null) {
            return null;
        }

        return $this->topics->find($topic, $user) !== null ? $topic : null;
    }
}
