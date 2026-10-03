<?php
/*
 * Created on   : Sat Oct 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PageHelpAccess.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Help;

use Illuminate\Http\Request;

/**
 * Seitenzugriff schaltet die Hilfe der Seite frei (Feature 039): Wer eine
 * Seite öffnen darf, liest ihr Hilfethema auch außerhalb der Zielgruppe.
 * Rollenlisten bilden eigene Rollen und einzeln vergebene Rechte nicht ab.
 *
 * Das Layout merkt das Thema an der Anfrage vor; freigegeben wird es erst
 * nach erfolgreicher Antwort ({@see \App\Http\Middleware\RememberPageHelp}).
 */
final class PageHelpAccess {
    public const SESSION_KEY = 'help_page_topics';

    private const REQUEST_KEY = 'help.page_topic';

    public function mark(Request $request, string $topic): void {
        $request->attributes->set(self::REQUEST_KEY, $topic);
    }

    public function remember(Request $request): void {
        $topic = $request->attributes->get(self::REQUEST_KEY);
        if (! is_string($topic) || ! $request->hasSession()) {
            return;
        }

        $topics = (array) $request->session()->get(self::SESSION_KEY, []);
        if (! in_array($topic, $topics, true)) {
            $topics[] = $topic;
            $request->session()->put(self::SESSION_KEY, $topics);
        }
    }

    public function allows(Request $request, string $topic): bool {
        return $request->hasSession()
            && in_array($topic, (array) $request->session()->get(self::SESSION_KEY, []), true);
    }
}
