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

use App\Models\Platform\{HelpTopic, User};
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
        private readonly PageHelpAccess $pageAccess,
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

        return $name === null || $name === '' ? null : $this->topicForRouteName($name);
    }

    /** Topic-Code eines Routennamens laut Registry, ohne Sichtbarkeits-Check. */
    public function topicForRouteName(string $name): ?string {
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
     * Topic existiert und der Nutzer es lesen darf: über die Zielgruppe oder,
     * auf einer geöffneten Seite, über den Seitenzugriff. Damit erscheint im
     * Layout nie ein "toter" Hilfe-Button.
     */
    public function visibleTopicFor(Request|Route $routeOrRequest, ?User $user): ?string {
        $topic = $this->currentTopicFor($routeOrRequest);
        if ($topic === null) {
            return null;
        }
        if ($this->topics->find($topic, $user) !== null) {
            return $topic;
        }

        if ($routeOrRequest instanceof Request && $user !== null && $this->topics->findForPage($topic) !== null) {
            $this->pageAccess->mark($routeOrRequest, $topic);

            return $topic;
        }

        return null;
    }

    /** Thema für den Abruf: sichtbar über die Zielgruppe oder über eine geöffnete Seite freigegeben. */
    public function readableTopic(Request $request, string $topic, ?User $user): ?HelpTopic {
        return $this->topics->find($topic, $user)
            ?? ($this->pageAccess->allows($request, $topic) ? $this->topics->findForPage($topic) : null);
    }
}
